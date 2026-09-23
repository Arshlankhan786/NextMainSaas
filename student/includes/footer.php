</div><!-- /page-content -->
</div><!-- /app-shell -->

<!-- Bootstrap JS (bundle with Popper — required for modals, dropdowns, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ── Mobile sidebar open/close ───────────────────────────── */
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('active');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
  document.body.style.overflow = '';
}

/* ── Auto-dismiss alert blocks ───────────────────────────── */
document.querySelectorAll('.btn-close-alert').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var el = this.closest('.alert-block');
    if (el) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(-4px)';
      el.style.transition = 'opacity 0.3s, transform 0.3s';
      setTimeout(function() { el.remove(); }, 320);
    }
  });
});

/* ── Close sidebar when resizing to desktop ──────────────── */
window.addEventListener('resize', function() {
  if (window.innerWidth > 768) closeSidebar();
});
</script>

<?php ob_end_flush(); ?>
</body>
</html>