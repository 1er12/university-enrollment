    </main>
  </div><!-- /app-main -->
</div><!-- /app-wrapper -->

<!-- Reusable confirmation dialog: add data-confirm="message" to a submit button -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <i class="bi bi-exclamation-triangle text-warning fs-1"></i>
        <p class="mt-2 mb-0" id="confirmMessage">Are you sure?</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="confirmOk">Confirm</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
