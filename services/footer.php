    </div> <!-- End Main Content -->
</div> <!-- End wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.getElementById("sidebarCollapse").addEventListener("click", function () {
    if (window.innerWidth <= 768) {
        document.getElementById("sidebar").classList.toggle("show");
    } else {
        document.getElementById("sidebar").classList.toggle("collapsed");
    }
});
</script>

</body>
</html>