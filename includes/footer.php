            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/main.js"></script>
    
    <!-- Additional JavaScript for specific pages -->
    <?php if (isset($additionalJS)): ?>
        <?= $additionalJS ?>
    <?php endif; ?>
    
    <!-- Page specific scripts -->
    <?php if (isset($pageScripts)): ?>
        <script>
            <?= $pageScripts ?>
        </script>
    <?php endif; ?>
</body>
</html>