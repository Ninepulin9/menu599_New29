<!DOCTYPE html>
<html lang="th" class="light-style layout-menu-fixed" dir="ltr"
      data-theme="theme-default" data-assets-path="{{ asset('assets/') }}" data-template="vertical-menu-template-free">

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
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{asset('assets/vendor/fonts/boxicons.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/css/core.css')}}" class="template-customizer-core-css" />
  <link rel="stylesheet" href="{{asset('assets/vendor/css/theme-default.css')}}" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="{{asset('assets/css/demo.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
  <link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />
  <script src="{{asset('assets/vendor/js/helpers.js')}}"></script>
  <script src="{{asset('assets/js/config.js')}}"></script>

  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800;900&family=Noto+Sans+Thai:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>

  <style>
    body { font-family: "Noto Sans Thai", sans-serif; font-optical-sizing: auto; }
    #orderNotifications { position: fixed; top: 1rem; right: 1rem; z-index: 20000; }
    .order-alert { position: relative; background:#fff; border-left:4px solid #0d6efd; box-shadow:0 2px 6px rgba(0,0,0,.2);
      padding:.5rem 1.5rem .5rem .75rem; margin-bottom:.5rem; cursor:pointer; min-width:260px; }
    .order-alert .close { position:absolute; top:4px; right:6px; font-size:1.2rem; line-height:1; cursor:pointer; }
  </style>

  @yield('style')
</head>

<body>
  <!-- เสียงแจ้งเตือน -->
  <audio id="notifySound" src="{{asset('sound/test.mp3')}}" preload="auto" playsinline></audio>
  <div id="orderNotifications"></div>

  @if ($message = Session::get('success'))
  <script>Swal.fire({icon:'success',title:@json($message),timer:1500,showConfirmButton:false});</script>
  @endif
  @if ($message = Session::get('error'))
  <script>Swal.fire({icon:'error',title:@json($message),timer:1500,showConfirmButton:false});</script>
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
    // ---------- SweetAlert Toast (ไม่บล็อก flow) ----------
    const Toast = Swal.mixin({
      toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, timerProgressBar: true,
      didOpen: (t)=>{ t.parentElement.style.zIndex = '2147483647'; }
    });

    // ---------- เสียง: ป้องกัน NotAllowedError และไม่ throw ----------
    function playNotify() {
      const el = document.getElementById('notifySound'); if (!el) return;
      try {
        el.currentTime = 0;
        const p = el.play();
        if (p && typeof p.then === 'function') {
          p.catch(()=>{/* เงียบไว้ ไม่ให้บล็อกกระบวนการ */});
        }
      } catch (_) { /* เงียบ */ }
    }

    // ---------- แสดงกล่องแจ้งรายการ (optional) ----------
    function showOrderNotification(order) {
      if (!order) return;
      const container = document.getElementById('orderNotifications'); if (!container) return;
      const box = document.createElement('div'); box.className = 'order-alert';
      const title = order.table_number ? `โต๊ะ ${order.table_number}` : 'ออเดอร์ออนไลน์';
      const items = (order.items || []).join(', ');
      box.innerHTML = `<strong>${title}</strong><br>${items}<br><small>${order.created_at||''}</small><span class="close">&times;</span>`;
      box.querySelector('.close').addEventListener('click', e=>{ e.stopPropagation(); box.remove(); });
      box.addEventListener('click', ()=>{ const url = order.is_online ? `/admin/order_rider?highlight=${order.id}` : `/admin/order?highlight=${order.table_number}`; window.location.href = url; });
      container.appendChild(box);
    }

    // ---------- เช็คออเดอร์ใหม่ & สั่งปริ้น (return Promise) ----------
    function checkNewOrders() {
      return fetch("{{ route('checkNewOrders') }}")
        .then(r => r.json())
        .then(res => {
          if (res.status) {
            if (res.table_id) {
              // สั่งปริ้นก่อน (สำคัญ)
              window.open('/admin/order/printOrderAdminCook/' + res.table_id, '_blank');
            }
            if (res.order) showOrderNotification(res.order);
          }
          return res;
        })
        .catch(err => { console.error(err); return { status:false, error:err }; });
    }

    // ---------- Pusher: ปริ้นก่อน → ค่อย Popup + เสียง ----------
    const PUSHER_APP_KEY = "{{ env('PUSHER_APP_KEY') }}";
    const PUSHER_APP_CLUSTER = "{{ env('PUSHER_APP_CLUSTER') }}";
    Pusher.logToConsole = true;

    const pusher = new Pusher(PUSHER_APP_KEY, { cluster: PUSHER_APP_CLUSTER, encrypted: true });
    const channel = pusher.subscribe('orders');

    channel.bind('App\\Events\\OrderCreated', async function (data) {
      const title = (data && data.order && data.order[0]) ? data.order[0] : 'มีออเดอร์ใหม่';

      // 1) ปริ้นก่อน (รอให้คำสั่งยิงออกไป)
      await checkNewOrders();

      // 2) แล้วค่อยเด้ง Toast + เล่นเสียง (ไม่ block)
      setTimeout(() => {
        playNotify();
        Toast.fire({ icon: 'info', title });
      }, 150);
    });

    // ---------- Interval สำรอง (ยังคงไว้ได้) ----------
    setInterval(checkNewOrders, 3000);

    // ---------- รับสัญญาณปริ้นเสร็จแล้ว ----------
    window.addEventListener('message', function (e) {
      if (e.data === 'cook-print-done') {
        Toast.fire({ icon: 'success', title: 'ปริ้น Order ในครัวแบบออโต้เรียบร้อยแล้ว' });
      }
    });
  </script>

  @yield('script')
</body>
</html>
