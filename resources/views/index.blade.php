@extends('layouts.master-home')

@section('title', 'Matoxi')

@section('css')
<link href="{{ URL::asset('build/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css" />
<style>
  body {
    background-color: #ffffff !important;
  }
  .main-content {
    padding-top: 2rem;
    background-color: #ffffff;
    min-height: 100vh;
  }
  .mega-menu-widgets {
    padding-top: 3rem;
  }
  .module-card {
    transition: all 0.3s ease;
  }
  .module-card.hidden {
    display: none;
  }
  .swiper {
    width: 100%;
    padding-bottom: 40px;
  }
  .swiper-slide {
    height: auto;
  }
  .swiper-pagination {
    position: relative;
    margin-top: 20px;
  }
  .swiper-wrapper {
    display: flex;
  }
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }
  @media (max-width: 992px) {
    .cards-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }
  @media (max-width: 576px) {
    .cards-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
@endsection

@section('content')
<div class="mega-menu-widgets container-fluid bg-white">
  <div class="row mb-4 mt-3">
    <div class="col-12">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="position-relative">
            <input type="text" id="moduleSearch" class="form-control form-control-lg rounded-5 px-5" placeholder="Cari module..." autocomplete="off">
            <span class="material-icons-outlined position-absolute ms-3 translate-middle-y start-0 top-50" style="color: #6c757d;">search</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Hidden container to store original cards -->
  <div id="originalCardsContainer" style="display: none;">
    <div class="module-card" data-module-name="marketing">
      <a href="{{ route('maps.map') }}">
        <div class="card rounded-4 shadow-none border mb-0">
          <div class="card-body">
            <div class="d-flex align-items-start gap-3">
              <img src="{{ URL::asset('build/images/global.jpg') }}" width="40" alt="">
              <div class="mega-menu-content">
                <h5>Hazard In Motion</h5>
                <p class="mb-0 f-14">Tranformasi Pengawasan Operasional berbasis Data-Driven Spatiotemporal untuk manajemen keselamatan pertambangan yang mengintegrasikan spatial & temporal</p>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <div class="module-card" data-module-name="website">
      <a href="">
        <div class="card rounded-4 shadow-none border mb-0">
          <div class="card-body">
            <div class="d-flex align-items-start gap-3">
              <img src="{{ URL::asset('build/images/hazrad.jpg') }}" width="40" alt="">
              <div class="mega-menu-content">
                <h5>Validasi Tbc Hazard</h5>
                <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                  the visual form of a document.</p>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <div class="module-card" data-module-name="subscribers">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/daily.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Daily Report</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="hubspot">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/weekly.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Weekly Report</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="templates">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/11.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Monthly Report</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="ebooks">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/13.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Investigasi</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="sales">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/12.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Sales</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="tools">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/08.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Tools</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="academy">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Academy</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
     <div class="module-card" data-module-name="cheat">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Cheating Simak K3l</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
     <div class="module-card" data-module-name="coverage">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/coverage.jpg') }}" width="60" alt="">
            <div class="mega-menu-content">
              <h5>Coverage Pengawasan Pengawas Safety</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="cuti">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Cuti</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="module-card" data-module-name="door">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Door To Door</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

      <div class="module-card" data-module-name="ikk">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>IKK</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

     <div class="module-card" data-module-name="cctv">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Instalasi Cctv</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="module-card" data-module-name="dms">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Instalasi Dms</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

      <div class="module-card" data-module-name="ca">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Nilai Ca Ko</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    

    <div class="module-card" data-module-name="k3l">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Nilai Evaluasi K3l</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

      <div class="module-card" data-module-name="commissioning">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Pelaksanaan Commissioning</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>


     <div class="module-card" data-module-name="project">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Pelaksanaan Project</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

  

    <div class="module-card" data-module-name="sidak">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Pelaksanaan Sidak Mess</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>


    <div class="module-card" data-module-name="lv">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Pembatasan Lv</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

     <div class="module-card" data-module-name="rekayasa">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Pemenuhan Pengendalian Rekayasa</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

     <div class="module-card" data-module-name="regulasi">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Pemenuhan Regulasi</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

      <div class="module-card" data-module-name="perizinan">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Perizinan Jasa Usaha</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>


   

     <div class="module-card" data-module-name="road">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Road Management</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>



     <div class="module-card" data-module-name="speak">
      <div class="card rounded-4 shadow-none border mb-0">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <img src="{{ URL::asset('build/images/megaIcons/09.png') }}" width="40" alt="">
            <div class="mega-menu-content">
              <h5>Speak UP</h5>
              <p class="mb-0 f-14">In publishing and graphic design, Lorem ipsum is a placeholder text commonly used to demonstrate
                the visual form of a document.</p>
            </div>
          </div>
        </div>
      </div>
    </div>


 
    


  </div>
  
  <div class="swiper mySwiper" id="moduleContainer">
    <div class="swiper-wrapper">
      <!-- Cards will be dynamically generated by JavaScript -->
    </div>
    <div class="swiper-pagination"></div>
  </div>
</div>
@endsection 
@section('scripts')

  <script src="{{ URL::asset('build/plugins/apexchart/apexcharts.min.js') }}"></script>
  <script src="{{ URL::asset('build/js/index.js') }}"></script>
  <script src="{{ URL::asset('build/plugins/peity/jquery.peity.min.js') }}"></script>
  <script src="{{ URL::asset('build/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
  <script src="{{ URL::asset('build/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>
  <script>
    $(".data-attributes span").peity("donut")
    
    // Initialize Swiper
    var swiper = new Swiper(".mySwiper", {
      slidesPerView: 1,
      spaceBetween: 30,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
    });
    
    // Function to organize cards into pages of 9
    function organizeCardsIntoPages(searchTerm) {
      var $wrapper = $('.swiper-wrapper');
      var $originalContainer = $('#originalCardsContainer');
      var $allCards = $originalContainer.find('.module-card');
      var visibleCards = [];
      
      // Filter cards based on search term
      $allCards.each(function() {
        var $card = $(this);
        var moduleName = $card.data('module-name').toLowerCase();
        var moduleTitle = $card.find('h5').text().toLowerCase();
        var moduleDesc = $card.find('p').text().toLowerCase();
        
        if (!searchTerm || searchTerm === '' || moduleName.includes(searchTerm) || moduleTitle.includes(searchTerm) || moduleDesc.includes(searchTerm)) {
          visibleCards.push($card.clone());
        }
      });
      
      var cardsPerPage = 9;
      var totalPages = Math.ceil(visibleCards.length / cardsPerPage);
      
      // Clear existing slides
      $wrapper.empty();
      
      // Create pages
      for (var i = 0; i < totalPages; i++) {
        var $slide = $('<div class="swiper-slide"></div>');
        var $grid = $('<div class="cards-grid"></div>');
        
        var startIndex = i * cardsPerPage;
        var endIndex = Math.min(startIndex + cardsPerPage, visibleCards.length);
        
        for (var j = startIndex; j < endIndex; j++) {
          $grid.append(visibleCards[j]);
        }
        
        $slide.append($grid);
        $wrapper.append($slide);
      }
      
      // Update Swiper
      swiper.update();
    }
    
    // Search functionality for modules
    $(document).ready(function() {
      // Initial organization
      organizeCardsIntoPages('');
      
      $('#moduleSearch').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        organizeCardsIntoPages(searchTerm);
      });
    });
  </script>

@endsection 





