<div class="modal fade" id="modal-receta" tabindex="-1" aria-labelledby="titulo-receta" aria-hidden="true">
<div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" id="form-receta">
<div class="modal-header"><h2 class="modal-title fs-5" id="titulo-receta">Preparación</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
<div class="modal-body">
<p class="small text-secondary">Estas indicaciones son para el barista. La receta original se conserva y el stock se descuenta según esa receta.</p>
<label for="unidad-receta" class="form-label">Unidad que deseas personalizar</label><select id="unidad-receta" class="form-select mb-3"></select>
<div id="insumos-receta"></div>
<label for="nota-receta" class="form-label">Indicación particular</label><textarea id="nota-receta" class="form-control" maxlength="500" rows="2"></textarea>
<p id="error-receta" class="text-danger" role="alert"></p>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" id="restablecer-receta">Restablecer</button><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-cafe" type="submit">Guardar indicación</button></div>
</form></div></div>
