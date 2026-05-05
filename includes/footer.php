    </main>

    <footer class="footer">
        <div class="container">
            <div class="flex-between">
                <div>
                    <strong><?= htmlspecialchars(APP_NAME) ?></strong><br>
                    <span>Laboratory Reservation & Station Management System</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Global JS -->
    <script src="<?= ASSETS_URL ?>js/main.js"></script>
    <script src="<?= ASSETS_URL ?>js/ajax.js"></script>

    <!-- Optional Page JS -->
    <?php if (!empty($pageJs)): ?>
        <script src="<?= ASSETS_URL ?>js/<?= htmlspecialchars($pageJs) ?>"></script>
    <?php endif; ?>

</body>
</html>