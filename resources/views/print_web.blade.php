<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order</title>
    <!-- QZ Tray (fallback ถ้ามี) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qz-tray/2.2.4/qz-tray.min.js" integrity="sha512-W1YQ2YsmEpRhtXZW8DqRLVQjaxAg/P6MqxsVXni4eWh05rq6ArlTc95xJMu38xpv8uKXu95syEHCqB6f+GO6wg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        @media print {
            @page { size: A4; margin: 1cm; }
            body { margin: 0; padding: 0; font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.4; }
            .no-print { display: none !important; }
        }
        body { font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; background-color: #f0f0f0; }
        .print-wrapper { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; background-color: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .no-print { display: block; text-align: center; padding: 10px 20px; }
        .badge { display:inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; vertical-align: middle; }
        .badge.ok { background:#d4edda; color:#155724; }
        .badge.err { background:#fdecea; color:#611a15; }
        /* สำหรับ preview HTML จาก payload */
        .pv-line { border-top: 1px dashed #000; margin: 8px 0; }
        .pv-text { margin: 2px 0; }
        .pv-text.bold { font-weight: bold; }
        .pv-text.size2 { font-size: 18px; }
        .pv-row { display: flex; width: 100%; }
        .pv-col { padding: 2px 4px; box-sizing: border-box; }
        .pv-right { text-align: right; }
        .pv-center { text-align: center; }
        .pv-table { border: 1px solid #000; border-collapse: collapse; width:100%; margin:10px 0; }
        .pv-table th, .pv-table td { border:1px solid #000; padding:6px; }
        .pv-table th { background:#f5f5f5; text-align:center; font-weight: bold; }
        .preview-mode { background-color: #f8f9fa !important; padding: 20px; }
        .preview-mode .print-wrapper { margin: 0 auto; background-color: white; box-shadow: 0 0 15px rgba(0,0,0,0.1); border-radius: 8px; padding: 20px; max-width: 600px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button id="btnPrint" style="padding: 10px 20px; font-size: 14px;">พิมพ์</button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; margin-left: 10px;">ปิด</button>
        <span id="printerStatus" style="margin-left:12px;">สถานะเครื่องพิมพ์: <span class="badge err">ไม่ทราบ</span></span>
    </div>

    <div class="print-wrapper">
        <div id="print-content"><!-- HTML preview จาก payload --></div>
    </div>

    <script>
        // ====== รับข้อมูลจาก Blade ======
        const jsonData = {!! $jsonData !!};
        const data = jsonData;

        // ====== Flag ความสามารถ (ตอนนี้ปิด image/qrcode/barcode ตาม requirement) ======
        const FEATURE_IMAGE   = false;
        const FEATURE_QRCODE  = false;
        const FEATURE_BARCODE = false;

        // ====== Utility ======
        const isInIframe = window.self !== window.top;

        function formatPrice(n) {
            const num = parseFloat(n || 0);
            return num.toFixed(2);
        }
        function formatDateTime(dateTime) {
            const date = new Date(dateTime);
            return date.toLocaleDateString('th-TH') + ' ' + date.toLocaleTimeString('th-TH');
        }
        function groupOrderItems(orderItems) {
            const grouped = {};
            (orderItems || []).forEach(item => {
                let key = `${item.menu ? item.menu.name : 'เมนู'}_${item.price}`;
                if (item.option && item.option.length > 0) {
                    const optionKeys = item.option.map(opt => opt?.option?.type || '').sort().join(',');
                    key += `_${optionKeys}`;
                }
                if (item.remark) key += `_${item.remark}`;
                if (grouped[key]) grouped[key].quantity = parseInt(grouped[key].quantity) + parseInt(item.quantity || 0);
                else grouped[key] = { ...item, quantity: parseInt(item.quantity || 0) };
            });
            return Object.values(grouped);
        }

        // ====== JSBridge (ตามสเปค) ======
        function getBridge() {
            if (window.posRegisterInterface) return window.posRegisterInterface; 
            if (window.webkit?.messageHandlers?.posRegisterInterface) return window.webkit.messageHandlers.posRegisterInterface; 
            return null;
        }
        function sendCommand(command, payload = []) {
            const message = { command, payload };
            const bridge = getBridge();
            if (!bridge) return false;

            try {
                if (typeof bridge.postMessage === 'function') {
                    // iOS
                    bridge.postMessage(JSON.stringify(message));
                } else if (typeof bridge.sendRequest === 'function') {
                    // Android
                    bridge.sendRequest(JSON.stringify(message));
                } else {
                    return false;
                }
                return true;
            } catch (e) {
                console.error('sendCommand error:', e);
                return false;
            }
        }

        // Native จะเรียกอันนี้กลับมา
        // ตัวอย่าง: onPrinterStatusUpdate(true);
        let PRINTER_ONLINE = null;
        window.onPrinterStatusUpdate = function(online) {
            PRINTER_ONLINE = !!online;
            const el = document.getElementById('printerStatus');
            if (!el) return;
            el.innerHTML = 'สถานะเครื่องพิมพ์: ' + (PRINTER_ONLINE
                ? '<span class="badge ok">พร้อมพิมพ์</span>'
                : '<span class="badge err">ไม่พร้อม/ไม่เชื่อมต่อ</span>');
        };

        function checkPrinterStatus() {
            // ถ้ามี Bridge ก็ส่ง STATUS_PRINTER
            if (getBridge()) {
                sendCommand("STATUS_PRINTER", []);
                // NOTE: รอ native เรียก onPrinterStatusUpdate(true/false) กลับมา
            } else {
                // ไม่มี bridge
                window.onPrinterStatusUpdate(false);
            }
        }

        // ====== สร้าง payload ตามสเปค PRINT_START ======
        function buildHeaderBlock(title, paymentNumber, createdAt) {
            const items = [];
            items.push({ type: "text", data: title, align: "center", bold: true, size: 2 });
            items.push({ type: "newline" });
            if (paymentNumber) items.push({ type: "text", data: `เลขที่ใบเสร็จ #${paymentNumber}`, align: "center" });
            if (createdAt)     items.push({ type: "text", data: `วันที่: ${formatDateTime(createdAt)}`, align: "center" });
            items.push({ type: "line", bold: true });
            return items;
        }

        function buildTableHeader() {
            return [{
                type: "table",
                columns: [
                    { text: "สินค้า",   width: 60 },
                    { text: "Qty",     width: 20 },
                    { text: "ราคารวม", width: 20 }
                ]
            }];
        }

        function buildTableRowsFromOrder(order) {
            const rows = [];
            const groupedItems = groupOrderItems(order);
            groupedItems.forEach(it => {
                const total = parseFloat(it.price || 0) * parseInt(it.quantity || 0);
                rows.push({
                    type: "table",
                    columns: [
                        { text: composeItemName(it), width: 60 },
                        { text: String(it.quantity || 0), width: 20 },
                        { text: formatPrice(total), width: 20 }
                    ]
                });
            });
            return rows;
        }

        function composeItemName(item) {
            let name = (item.menu && item.menu.name) ? item.menu.name : 'เมนู';
            if (item.option && item.option.length > 0) {
                const opts = item.option
                    .map(o => o?.option?.type)
                    .filter(Boolean);
                if (opts.length) name += ` (+${opts.join(', ')})`;
            }
            if (item.remark) name += ` [${item.remark}]`;
            return name;
        }

        function buildTotalsBlock(total, vatRate = null) {
            const items = [];
            items.push({ type: "line", bold: true });
            if (vatRate !== null) {
                const vat = total * vatRate;
                const grand = total + vat;
                items.push({ type: "text", data: `ยอดรวม: ${formatPrice(total)} ฿`, align: "right", bold: true });
                items.push({ type: "text", data: `ภาษีมูลค่าเพิ่ม ${Math.round(vatRate*100)}%: ${formatPrice(vat)} ฿`, align: "right", bold: true });
                items.push({ type: "text", data: `รวมทั้งสิ้น: ${formatPrice(grand)} ฿`, align: "right", bold: true, size: 2 });
            } else {
                items.push({ type: "text", data: `Total: ${formatPrice(total)} ฿`, align: "right", bold: true, size: 2 });
            }
            items.push({ type: "newline" });
            return items;
        }

        function calcTotal(order) {
            const groupedItems = groupOrderItems(order || []);
            return groupedItems.reduce((sum, it) => sum + parseFloat(it.price || 0) * parseInt(it.quantity || 0), 0);
        }

        // สร้าง payload ตามชนิดใบเสร็จใน data.type
        function buildPrintPayloadByType(data) {
            const payload = [];
            const type = data?.type || 'normal';

            if (type === 'normal') {
                payload.push(...buildHeaderBlock(data?.config?.name || 'ใบเสร็จรับเงิน', data?.pay?.payment_number, data?.pay?.created_at));
                payload.push(...buildTableHeader());
                payload.push(...buildTableRowsFromOrder(data?.order || []));
                const total = parseFloat(data?.pay?.total ?? calcTotal(data?.order || []));
                payload.push(...buildTotalsBlock(total, null));
            }
            else if (type === 'taxfull' || type === 'tax_full' || type === 'tax') {
                payload.push(...buildHeaderBlock((data?.config?.name || 'ร้านอาหาร') + ' - ใบกำกับภาษี', data?.pay?.payment_number, data?.pay?.created_at));
                if (data?.tax_full) {
                    payload.push({ type: "text", data: `ชื่อ: ${data.tax_full.name}`, align: "left" });
                    payload.push({ type: "text", data: `เบอร์โทร: ${data.tax_full.tel}`, align: "left" });
                    payload.push({ type: "text", data: `เลขภาษี: ${data.tax_full.tax_id}`, align: "left" });
                    payload.push({ type: "text", data: `ที่อยู่: ${data.tax_full.address}`, align: "left" });
                    payload.push({ type: "line", bold: true });
                }
                payload.push(...buildTableHeader());
                payload.push(...buildTableRowsFromOrder(data?.order || []));
                const total = calcTotal(data?.order || []);
                payload.push(...buildTotalsBlock(total, 0.07));
            }
            else if (type === 'order_admin') {
                payload.push(...buildHeaderBlock(data?.config?.name || 'สรุปออเดอร์ (แอดมิน)', `โต๊ะ #${data?.table?.table_number || data?.table_id || '-'}`, new Date()));
                payload.push(...buildTableHeader());
                payload.push(...buildTableRowsFromOrder(data?.order_details || []));
                const total = calcTotal(data?.order_details || []);
                payload.push(...buildTotalsBlock(total, null));
            }
            else if (type === 'order_cook') {
                payload.push({ type: "text", data: data?.config?.name || 'ออเดอร์ครัว', align: "center", bold: true, size: 2 });
                payload.push({ type: "text", data: `โต๊ะ #${data?.table?.table_number || data?.table_id || '-'}`, align: "center", bold: true });
                payload.push({ type: "text", data: `วันที่: ${formatDateTime(new Date())}`, align: "center" });
                payload.push({ type: "line", bold: true });
                const groupedItems = groupOrderItems(data?.order_details || []);
                if (groupedItems.length === 0) {
                    payload.push({ type: "text", data: '— ไม่มีรายการ —', align: "center" });
                } else {
                    groupedItems.forEach((it, idx) => {
                        payload.push({ type: "text", data: `${idx+1}. ${composeItemName(it)}`, align: "left", bold: true, size: 2 });
                        payload.push({ type: "text", data: `จำนวน: ${it.quantity}`, align: "left", bold: true });
                        payload.push({ type: "line" });
                    });
                }
            }
            else {
                // default -> normal
                return buildPrintPayloadByType({ ...data, type: 'normal' });
            }

            // ไม่มี QR/Barcode/Image ใน payload (ตาม requirement)
            return payload.filter(it => {
                if (it.type === 'image'   && !FEATURE_IMAGE) return false;
                if (it.type === 'qrcode'  && !FEATURE_QRCODE) return false;
                if (it.type === 'barcode' && !FEATURE_BARCODE) return false;
                return true;
            });
        }

        // ====== Preview HTML จาก payload (ใช้แสดงผล + QZ fallback) ======
        function payloadToHTML(payload) {
            let html = '';
            const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
            payload.forEach(item => {
                switch (item.type) {
                    case 'text': {
                        const cls = [
                            'pv-text',
                            item.bold ? 'bold' : '',
                            item.size === 2 ? 'size2' : ''
                        ].join(' ').trim();
                        const align = item.align === 'right' ? 'pv-right' : item.align === 'center' ? 'pv-center' : '';
                        html += `<div class="${cls} ${align}">${esc(item.data)}</div>`;
                        break;
                    }
                    case 'newline': {
                        html += '<br>';
                        break;
                    }
                    case 'line': {
                        html += `<div class="pv-line"></div>`;
                        break;
                    }
                    case 'table': {
                        // simple flex row
                        html += `<div class="pv-row">`;
                        (item.columns || []).forEach(col => {
                            const w = Math.max(0, Math.min(100, parseFloat(col.width || 0)));
                            html += `<div class="pv-col" style="width:${w}%;">${esc(col.text ?? '')}</div>`;
                        });
                        html += `</div>`;
                        break;
                    }
                    case 'image':
                    case 'qrcode':
                    case 'barcode': {
                        // ปิดไว้ ไม่แสดง
                        break;
                    }
                    default: break;
                }
            });
            return `<div>${html}</div>`;
        }

        // ====== QZ Tray ======
        if (window.qz) {
            qz.security.setCertificatePromise((resolve) => resolve());
            qz.security.setSignaturePromise(() => (resolve) => resolve());
        }
        function printViaQZ(html) {
            if (!window.qz) return Promise.reject(new Error('QZ not found'));
            return qz.websocket.connect()
                .then(() => qz.printers.getDefault())
                .then(printer => {
                    const cfg = qz.configs.create(printer);
                    return qz.print(cfg, [{ type: 'html', format: 'plain', data: html }]);
                })
                .finally(() => { try { qz.websocket.disconnect(); } catch(e){} });
        }

        // ====== พิมพ์ผ่าน JSBridge → Fallback ======
        function handlePrint() {
            // 1) JSBridge
            const payload = buildPrintPayloadByType(data);
            const sent = sendCommand("PRINT_START", payload);
            if (sent) return Promise.resolve();

            // 2) QZ Tray
            const html = `<html><head><meta charset="UTF-8"></head><body>${document.getElementById('print-content').innerHTML}</body></html>`;
            if (window.qz) {
                return printViaQZ(html).catch(err => {
                    console.warn('QZ error -> fallback window.print', err);
                    window.print();
                });
            }

            // 3) Browser
            window.print();
            return Promise.resolve();
        }

        // ====== Render preview ======
        function renderPreview() {
            const payload = buildPrintPayloadByType(data);
            document.getElementById('print-content').innerHTML = payloadToHTML(payload);
        }

        // ====== Auto flow ======
        document.addEventListener('DOMContentLoaded', () => {
            if (isInIframe) document.body.classList.add('preview-mode');

            renderPreview();
            checkPrinterStatus();

            document.getElementById('btnPrint').addEventListener('click', () => {
                handlePrint().catch(console.error);
            });

            // Auto print เฉพาะบางชนิด (ตามเดิม)
            if (!isInIframe) {
                const t = (data?.type || '').toLowerCase();
                if (t === 'order_cook') {
                    setTimeout(() => {
                        handlePrint().finally(() => {
                            setTimeout(() => {
                                if (window.opener) {
                                    window.opener.postMessage('cook-print-done', '*');
                                    window.close();
                                }else {
                                    const prev = (() => {
                                        try {
                                            const url = sessionStorage.getItem('admin-prev-url');
                                            sessionStorage.removeItem('admin-prev-url');
                                            return url;
                                        } catch (e) {
                                            return null;
                                        }
                                    })();
                                    if (prev) {
                                        window.location.href = prev;
                                    } else if (document.referrer) {
                                        window.location.href = document.referrer;
                                    } else {
                                        history.back();
                                    }
                                }
                            }, 800);
                        });
                    }, 1500);
                } else if (t === 'order_admin') {
                    setTimeout(() => { handlePrint(); }, 800);
                }
            }
        });

        // กัน error เงียบ
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e?.error || e);
            document.getElementById('print-content').innerHTML =
                '<div style="text-align: center; color: red; padding: 20px;">เกิดข้อผิดพลาดในการโหลดข้อมูล<br>กรุณาลองใหม่อีกครั้ง</div>';
        });
    </script>
</body>
</html>
