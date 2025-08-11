<!DOCTYPE html>
<html lang="th" class="light-style layout-menu-fixed" dir="ltr"
      data-theme="theme-default" data-assets-path="{{ asset('assets/') }}" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum=1.0, maximum=1.0" />
  <title>ระบบร้านค้า</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-channel" content="{{ request()->header('channel', '') }}">
  <meta name="app-device" content="{{ request()->header('device', '') }}">

  <link rel="icon" type="image/x-icon" href="{{asset('assets/img/favicon/favicon.ico')}}" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{asset('assets/vendor/fonts/boxicons.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/css/core.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/css/theme-default.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/css/demo.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />
  <script src="{{asset('assets/vendor/js/helpers.js')}}"></script>
  <script src="{{asset('assets/js/config.js')}}"></script>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600;700;800;900&family=Noto+Sans+Thai:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>

  <style>
    body { font-family: "Noto Sans Thai", sans-serif; font-optical-sizing: auto; }
    #orderNotifications { position: fixed; top: 1rem; right: 1rem; z-index: 2000; }
    .order-alert{ position:relative;background:#fff;border-left:4px solid #0d6efd;box-shadow:0 2px 6px rgba(0,0,0,.2);padding:.5rem 1.5rem .5rem .75rem;margin-bottom:.5rem;cursor:pointer;min-width:260px;}
    .order-alert .close{ position:absolute; top:4px; right:6px; font-size:1.2rem; cursor:pointer;}
    /* Toast ให้อยู่ใต้ขอบ navbar เสมอ */
    #layout-navbar{ position:sticky; top:0; z-index:1000; }
  </style>
  @yield('style')
</head>
<body>
  <audio id="notifySound" src="{{asset('sound/test.mp3')}}" preload="auto" playsinline></audio>
  <div id="orderNotifications"></div>

  @if ($message = Session::get('success'))
  <script>Swal.fire({icon:'success',title:@json($message),timer:2000,showConfirmButton:false});</script>
  @endif
  @if ($message = Session::get('error'))
  <script>Swal.fire({icon:'error',title:@json($message),timer:2500,showConfirmButton:false});</script>
  @endif

  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      @include('admin.menu')
      <div class="layout-page">
        @include('admin.navheader')
        @yield('content')
        <footer class="content-footer footer bg-footer-theme">
          <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
            <div class="mb-2 mb-md-0">© <script>document.write(new Date().getFullYear());</script>, So Fin By So Smart Solution</div>
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
    // ===== Toast (ไม่บังงาน, ไม่ต้องกด) =====
    function navbarBottomPx(){
      const nav = document.getElementById('layout-navbar') || document.querySelector('.layout-navbar');
      if(!nav) return 0;
      const r = nav.getBoundingClientRect();
      return Math.max(0, Math.round(r.bottom + window.scrollY));
    }
    const Toast = Swal.mixin({
      toast:true, position:'top-end', showConfirmButton:false, timer:2000, timerProgressBar:true,
      didOpen: el => { const c = el.parentElement; c.style.zIndex='2147483647'; c.style.top=(navbarBottomPx()+8)+'px'; c.style.right='12px'; }
    });
    window.addEventListener('resize', ()=>{ const c=document.querySelector('.swal2-container.swal2-top-end'); if(c){c.style.top=(navbarBottomPx()+8)+'px';}});

    // ===== เสียง (ไม่บังคับ gesture; ถ้าเล่นไม่ได้ ก็เงียบไป) =====
    function playNotify(){
      const el = document.getElementById('notifySound');
      if(!el) return;
      try{ el.currentTime = 0; el.play().catch(()=>{}); }catch(_){}
    }

    // ===== ป้ายแจ้งเตือนด้านขวา =====
    function showOrderNotification(order){
      if(!order) return;
      const container = document.getElementById('orderNotifications');
      const box = document.createElement('div');
      box.className = 'order-alert';
      const title = order.table_number ? `โต๊ะ ${order.table_number}` : 'ออเดอร์ออนไลน์';
      const items = (order.items || []).join(', ');
      box.innerHTML = `<strong>${title}</strong><br>${items}<br><small>${order.created_at||''}</small><span class="close">&times;</span>`;
      box.querySelector('.close').addEventListener('click', e=>{e.stopPropagation(); box.remove();});
      box.addEventListener('click', ()=>{
        const url = order.is_online ? `/admin/order_rider?highlight=${order.id}` : `/admin/order?highlight=${order.table_number}`;
        window.location.href = url;
      });
      container.appendChild(box);
      setTimeout(()=>box.remove(), 15000);
    }

    // ===== Auto Print: สั่งพิมพ์ก่อนเสมอ -> สำเร็จค่อย toast, ล้มเหลวค่อย popup error =====
    let printingLock = false;          // กันซ้ำ
    let autoPrintWatch = null;         // watchdog ถ้าไม่ส่งกลับภายในเวลา

    function tryAutoPrint(tableId){
      if(!tableId) return false;
      if(printingLock) return true;
      printingLock = true;

      const url = '/admin/order/printOrderAdminCook/' + tableId;
      const w = window.open(url, '_blank');   // ต้องเรียกก่อน popup อะไรทั้งนั้น
      if(!w){
        printingLock = false;
        Swal.fire({
          icon:'error',
          title:'สั่งปริ้นไม่สำเร็จ',
          text:'เบราว์เซอร์บล็อกหน้าต่างปริ้น อนุญาต Pop-up สำหรับโดเมนนี้ก่อน',
          confirmButtonText:'รับทราบ'
        });
        return false;
      }

      // ตั้ง watchdog ถ้าไม่มีสัญญาณกลับ ให้แจ้งเตือน
      if(autoPrintWatch){ clearTimeout(autoPrintWatch); }
      autoPrintWatch = setTimeout(()=>{
        printingLock = false;
        Swal.fire({
          icon:'warning',
          title:'ไม่ยืนยันผลการปริ้น',
          text:'ไม่ได้รับสัญญาณยืนยันจากหน้าปริ้น อาจปริ้นแล้วหรือถูกบล็อก',
          confirmButtonText:'ปิด'
        });
      }, 15000);

      // หลังสั่งปริ้นแล้วค่อยทำอย่างอื่น (เช่นเสียง/การ์ดแจ้งเตือน)
      playNotify();
      return true;
    }

    // ===== รับสัญญาณจากหน้า print แล้วค่อยสรุปผล =====
    window.addEventListener('message', function(e){
      if(e.data === 'cook-print-done'){
        if(autoPrintWatch){ clearTimeout(autoPrintWatch); autoPrintWatch=null; }
        printingLock = false;
        Toast.fire({icon:'success', title:'ปริ้นออเดอร์ในครัวแล้ว'});
      }
      if(e.data === 'cook-print-error'){
        if(autoPrintWatch){ clearTimeout(autoPrintWatch); autoPrintWatch=null; }
        printingLock = false;
        Swal.fire({icon:'error', title:'ปริ้นล้มเหลว', text:'กรุณาตรวจสอบเครื่องพิมพ์/การเชื่อมต่อ'});
      }
    });

    // ===== Poll API =====
    function checkNewOrders(){
      fetch("{{ route('checkNewOrders') }}")
        .then(r=>r.json())
        .then(res=>{
          if(!res || !res.status) return;
          // 1) พิมพ์ก่อน
          if(res.table_id){ tryAutoPrint(res.table_id); }
          // 2) ค่อยแสดงการ์ด/เสียง (ไม่บล็อกงาน)
          if(res.order){ showOrderNotification(res.order); }
        })
        .catch(err=>console.error(err));
    }
    setInterval(checkNewOrders, 1000);

    // ===== Pusher: รับ Event แล้ว "สั่งพิมพ์ก่อน" เสมอ =====
    const PUSHER_APP_KEY = "{{ env('PUSHER_APP_KEY') }}";
    const PUSHER_APP_CLUSTER = "{{ env('PUSHER_APP_CLUSTER') }}";
    Pusher.logToConsole = true;
    const pusher = new Pusher(PUSHER_APP_KEY, { cluster: PUSHER_APP_CLUSTER, encrypted:true });
    const channel = pusher.subscribe('orders');

    channel.bind('App\\Events\\OrderCreated', function(data){
      // ไม่ขึ้น popup ใดๆ ตรงนี้
      // สั่งเช็ค/พิมพ์ทันที
      checkNewOrders();
      // จะมีเสียงแบบ non-blocking หลัง tryAutoPrint ใน checkNewOrders
    });
  </script>

  @yield('script')
</body>
</html>
