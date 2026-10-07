<?php
// Admin Reusable Footer
?>
    </div> <!-- /.admin-content -->
  </div> <!-- /.admin-main -->

  <script src="../assets/js/main.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var sidebarToggle = document.getElementById('sidebarToggle');
      var adminSidebar = document.getElementById('adminSidebar');
      
      if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', function(e) {
          e.stopPropagation();
          adminSidebar.classList.toggle('show-sidebar');
        });

        document.addEventListener('click', function(e) {
          if (window.innerWidth <= 992 && !adminSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
            adminSidebar.classList.remove('show-sidebar');
          }
        });
      }
    });
  </script>
</body>
</html>
