        </main>
    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<?php if (!empty($useBootstrap)): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<?php if (!empty($useSweetAlert)): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php endif; ?>
<script src="<?= url('js/app.js') ?>"></script>
<script src="<?= url('js/pw-toggle.js') ?>"></script>
<?php foreach ($extraJs ?? [] as $jsFile): ?>
<script src="<?= url($jsFile) ?>"></script>
<?php endforeach; ?>
</body>
</html>
