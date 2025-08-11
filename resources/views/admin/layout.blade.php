<!DOCTYPE html>
<html lang="th" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="{{ asset('assets/') }}" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>ระบบร้านค้า</title>
  <meta name="description" content="" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-channel" content="{{ request()->header('channel', '') }}">
  <meta name="app-device" content="{{ request()->header('device', '') }}">

  <link rel="icon" type="image/x-icon" href="{{asset('assets/img/favicon/favicon.ico')}}" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{asset('assets/vendor/fonts/boxicons.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/css/core.css')}}" class="template-customizer-core-css" />
  <link rel="stylesheet" href="{{asset('assets/vendor/css/theme-default.css')}}" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="{{asset('assets/css/demo.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />
  <script src="{{asset('assets/vendor/js/helpers.js')}}"></script>
  <script src="{{asset('assets/js/config.js')}}"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Noto+Sans+Thai:wght@100..900&family=Sarabun:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>

  <style>
    body { font-family: "Noto Sans Thai", sans-serif; font-optical-sizing: auto; }
    #orderNotifications { position: fixed; top: 1rem; right: 1rem; z-index: 20000; }
    .order-alert { position: relative; background: #fff; border-left: 4px solid #0d6efd; box-shadow: 0 2px 6px rgba(0,0,0,0.2); padding: 0.5rem 1.5rem 0.5rem 0.75rem; margin-bottom: 0.5rem; cursor: pointer; min-width: 260px; }
    .order-alert .close { position: absolute; top: 4px; right: 6px; font-size: 1.2rem; line-height: 1; cursor: pointer; }
    .highlight-row { background: #fff3cd !important; }
    .swal2-container { z-index: 2147483647 !important; }
  </style>

  @yield('style')

  <script>
    const PUSHER_APP_KEY = "{{ env('PUSHER_APP_KEY') }}";
    const PUSHER_APP_CLUSTER = "{{ env('PUSHER_APP_CLUSTER') }}";

    Pusher.logToConsole = true;
    const pusher = new Pusher(PUSHER_APP_KEY, { cluster: PUSHER_APP_CLUSTER, encrypted: true });
    const channel = pusher.subscribe('orders');

    // ===== JSBridge helpers (Android/iOS) =====
    function getBridge() {
      if (window.posRegisterInterface) return window.posRegisterInterface; // Android
      if (window.webkit?.messageHandlers?.posRegisterInterface) return window.webkit.messageHandlers.posRegisterInterface; // iOS
      return null;
    }
    function sendCommand(command, payload = []) {
      const bridge = getBridge();
      if (!bridge) return false;
      const msg = JSON.stringify({ command, payload });
      try {
        if (typeof bridge.postMessage === 'function') { bridge.postMessage(msg); return true; }   // iOS
        if (typeof bridge.sendRequest === 'function') { bridge.sendRequest(msg); return true; }   // Android
      } catch (e) { console.warn('sendCommand error', e); }
      return false;
    }

    // ===== เสียงแจ้งเตือน (ไม่ให้ error มาบล็อก flow) =====
    function playNotify() {
      const el = document.getElementById('notifySound');
      if (!el) return;
      try {
        el.currentTime = 0;
        const p = el.play();
        if (p && typeof p.then === 'function') {
          p.catch(() => {
            const once = () => { el.currentTime = 0; el.play().catch(()=>{}); };
            window.addEventListener('click', once, { once: true, passive: true });
            window.addEventListener('touchstart', once, { once: true, passive: true });
          });
        }
      } catch (_) {}
    }
    function unlockAudioOnce() {
      const el = document.getElementById('notifySound');
      if (!el) return;
      const handler = () => {
        try { el.play().then(()=>{ el.pause(); el.currentTime=0; }).catch(()=>{}); } catch(_){}
        window.removeEventListener('click', handler);
        window.removeEventListener('touchstart', handler);
      };
      window.addEventListener('click', handler, { once: true, passive: true });
      window.addEventListener('touchstart', handler, { once: true, passive: true });
    }
    document.addEventListener('DOMContentLoaded', unlockAudioOnce);

    // ===== กล่องแจ้งเตือนมุมขวาบน =====
    function showOrderNotification(order) {
      if (!order) return;
      const container = document.getElementById('orderNotifications'); if (!container) return;
      const box = document.createElement('div');
      box.className = 'order-alert';
      const title = order.table_number ? `โต๊ะ ${order.table_number}` : 'ออเดอร์ออนไลน์';
      const items = (order.items || []).join(', ');
      box.innerHTML = `<strong>${title}</strong><br>${items}<br><small>${order.created_at||''}</small><span class="close">&times;</span>`;
      box.querySelector('.close').addEventListener('click', (e) => { e.stopPropagation(); box.remove(); });
      box.addEventListener('click', () => {
        const url = order.is_online ? `/admin/order_rider?highlight=${order.id}` : `/admin/order?highlight=${order.table_number}`;
        window.location.href = url;
      });
      container.appendChild(box);
    }

    // ===== helper หน่วงเวลา =====
    const sleep = (ms) => new Promise(r => setTimeout(r, ms));

    // ===== พยายามพิมพ์ผ่าน Bridge ก่อน (ต้องมี endpoint คืน payload PRINT_START) =====
    async function tryBridgePrint(tableId) {
      const bridge = getBridge();
      if (!bridge) return false;
      try {
        const res = await fetch(`/admin/order/print-payload/${tableId}`, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('payload fetch failed');
        const json = await res.json();
        const items = Array.isArray(json) ? json : (json.items || []);
        if (!Array.isArray(items) || items.length === 0) throw new Error('invalid items');
        return !!sendCommand('PRINT_START', items);
      } catch (e) {
        console.warn('Bridge print failed:', e);
        return false;
      }
    }

    // ===== เมื่อมีออเดอร์ใหม่: ปริ้นก่อน → หน่วง 1s → Popup+เสียง =====
    channel.bind('App\\Events\\OrderCreated', async function(data) {
      const title = (data && data.order && data.order[0]) ? data.order[0] : 'มีออเดอร์ใหม่';
      if (typeof checkNewOrders === 'function') { await checkNewOrders(); }
      setTimeout(() => {
        playNotify();
        Swal.fire({ icon: 'info', title: title, timer: 1000, showConfirmButton: false });
      }, 1000);
    });
  </script>
</head>

<body>
  <audio id="notifySound" src="{{asset('sound/test.mp3')}}" preload="auto" playsinline></audio>
  <div id="orderNotifications"></div>

  @if ($message = Session::get('success'))
  <script>
    Swal.fire({ icon: 'success', title: @json($message) });
  </script>
  @endif
  @if($message = Session::get('error'))
  <script>
    Swal.fire({ icon: 'error', title: @json($message) });
  </script>
  @endif

  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      @include('admin.menu')
      <div class="layout-page">
        @include('admin.navheader')
        @yield('content')
        <footer class="content-footer footer bg-footer-theme">
          <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
            <div class="mb-2 mb-md-0">
              © <script>document.write(new Date().getFullYear());</script>, So Fin By So Smart Solution
            </div>
          </div>
        </footer>
        <div class="content-backdrop fade"></div>
      </div>
    </div>
  </div>
  <div class="layout-overlay layout-menu-toggle"></div>

  <script src="{{asset('assets/vendor/libs/jquery/jquery.js')}}"></script>
  <script src="{{asset('assets/vendor/libs/popper/popper.js')}}"></script>
  <script src="{{asset('assets/vendor/js/bootstrap.js')}}"></script>
  <script src="{{asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
  <script src="{{asset('assets/vendor/js/menu.js')}}"></script>
  <script src="{{asset('assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
  <script src="{{asset('assets/js/main.js')}}"></script>
  <script src="{{asset('assets/js/dashboards-analytics.js')}}"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>

  <script>
    async function checkNewOrders() {
      try {
        const res = await fetch("{{ route('checkNewOrders') }}").then(r => r.json());
        if (res && res.status) {
          if (res.order) {
            showOrderNotification(res.order);
          }
          if (res.table_id) {
            // 1) ใช้ Bridge ก่อน
            const usedBridge = await tryBridgePrint(res.table_id);
            if (!usedBridge) {
              // 2) ไม่ได้ → หน่วง 1 วิ แล้วเปิดหน้าปริ้นแบบเดิม
              await new Promise(r => setTimeout(r, 1000));
              window.open('/admin/order/printOrderAdminCook/' + res.table_id, '_blank');
            }
          }
        }
        return res;
      } catch (err) {
        console.error(err);
        return { status: false, error: err };
      }
    }

    setInterval(checkNewOrders, 1000);

    window.addEventListener('message', function(e) {
      if (e.data === 'cook-print-done') {
        Swal.fire({
          icon: 'success',
          title: 'ปริ้น Order ในครัวแบบออโต้เรียบร้อยแล้ว',
          timer: 1000,
          showConfirmButton: false
        });
      }
    });
  </script>

  @yield('script')
</body>
</html>
