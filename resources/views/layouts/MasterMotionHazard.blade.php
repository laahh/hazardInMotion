<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--favicon-->
    <link rel="icon" href="{{ URL::asset('build/images/logo-removebg.png') }}" type="image/png">
    <title>@yield('title') | Laravel & Bootstrap 5 Admin Dashboard Template</title>

    <script>
      (function loadBotpress() {
        var inject = document.createElement('script');
        inject.src = 'https://cdn.botpress.cloud/webchat/v3.3/inject.js';
        inject.async = true;
        inject.onload = function() {
          var cfg = document.createElement('script');
          cfg.src = 'https://files.bpcontent.cloud/2025/10/23/01/20251023014904-08L72VH4.js';
          cfg.defer = true;
          cfg.onload = function() {
            setTimeout(function () {
              if (window.botpressWebChat && typeof window.botpressWebChat.init === 'function') {
                try {
                  window.botpressWebChat.init({
                    configUrl: 'https://files.bpcontent.cloud/2025/10/23/01/20251023014904-4YEA6SB3.json'
                  });
                } catch (e) {
                  console.error('Botpress manual init failed', e);
                }
              }
            }, 1500);
          };
          cfg.onerror = function() { console.error('Botpress config script failed to load'); };
          document.head.appendChild(cfg);
        };
        inject.onerror = function() { console.error('Botpress inject script failed to load'); };
        document.head.appendChild(inject);
      })();
    </script>

    @yield('css')

    @include('layouts.head-css')

</head>

<body>

@include('layouts.topbar')
@include('layouts.slideBarHazard')

<!--start main wrapper-->
<main class="main-wrapper">
    <div class="main-content">

        @yield('content')

    </div>
</main>
<!--end main wrapper-->

<!--start overlay-->
    <div class="overlay btn-toggle"></div>
<!--end overlay-->

  @include('layouts.footer')

  @include('layouts.cart')

  @include('layouts.right-sidebar')

  @include('layouts.vendor-scripts')

  @yield('scripts')

 
  
  <script>
    (function initBp(){
      var btn = document.getElementById('bp-toggle');
      if (!btn) return;

      function enableBtn() {
        if (!btn.disabled) return;
        btn.disabled = false;
        btn.addEventListener('click', function () {
          if (window.botpressWebChat) {
            if (typeof window.botpressWebChat.open === 'function') {
              window.botpressWebChat.open();
            } else if (typeof window.botpressWebChat.toggle === 'function') {
              window.botpressWebChat.toggle();
            } else {
              console.log('Botpress API belum siap');
            }
          } else {
            console.log('window.botpressWebChat belum ada');
          }
        }, { once: false });
      }

      function bindReady() {
        if (window.botpressWebChat && typeof window.botpressWebChat.onEvent === 'function') {
          window.botpressWebChat.onEvent(function () {
            enableBtn();
          }, ['LIFECYCLE.READY']);
          return true;
        }
        return false;
      }

      var tries = 0;
      (function waitApi(){
        tries++;
        if (bindReady() || (window.botpressWebChat && (window.botpressWebChat.open || window.botpressWebChat.toggle))) {
          enableBtn();
          return;
        }
        if (tries < 80) setTimeout(waitApi, 250);
      })();

      setTimeout(enableBtn, 5000);
    })();
  </script>
    

</body>
  
</html>
