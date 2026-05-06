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
    <script src="<?= asset_url('js/main.js') ?>"></script>
    <script src="<?= asset_url('js/ajax.js') ?>"></script>

    <!-- Optional Page JS -->
    <?php if (!empty($pageJs)): ?>
        <script src="<?= asset_url('js/' . $pageJs) ?>"></script>
    <?php endif; ?>

</body>
</html>