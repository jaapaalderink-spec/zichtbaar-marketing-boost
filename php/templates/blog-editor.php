<form method="post" enctype="multipart/form-data" class="panel admin-panel">
<h2><?= $post['id']===''?'Nieuw artikel':'Artikel bewerken' ?></h2>
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($post['id']) ?>"><input type="hidden" name="version" value="<?= e($post['version']) ?>">
<div class="editor-fields">
<label>Titel *<input name="title" required maxlength="250" value="<?= e($post['title']) ?>"></label>
<label>Korte samenvatting voor het overzicht<textarea name="excerpt" maxlength="600" rows="3"><?= e($post['excerpt']) ?></textarea></label>
<div class="blog-formatting" role="group" aria-label="Blogtekst opmaken" hidden>
<button type="button" class="button secondary small" data-heading="2">Kop H2</button>
<button type="button" class="button secondary small" data-heading="3">Subkop H3</button>
<button type="button" class="button secondary small" data-heading="4">Subkop H4</button>
<button type="button" class="button secondary small" data-heading="0">Gewone tekst</button>
<button type="button" class="button secondary small" data-bold><strong>Vet</strong></button>
</div>
<label>Volledige blogtekst<textarea id="blog-body" aria-describedby="blog-format-help" class="blog-writing" name="body" maxlength="100000" rows="20"><?= e($post['body']) ?></textarea></label>
<p id="blog-format-help" class="fine">Zet je cursor in een regel en kies een kop, of selecteer tekst en klik op Vet (Ctrl/Cmd+B). De opmaakcodes blijven zichtbaar tijdens het schrijven: ## voor H2, ### voor H3, #### voor H4 en **tekst** voor vet. Gebruik lege regels voor alinea’s. De artikeltitel is H1; bouw je koppen op van H2 naar H3 en H4. HTML wordt als gewone tekst weergegeven.</p>
<div><img class="blog-editor-image" src="<?= e(blog_image_url($post)) ?>" alt="Huidige artikelafbeelding"></div>
<label>Afbeelding uploaden (JPG, PNG of WebP, maximaal 8 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
<?php if($post['image']!==''): ?><label class="newsletter-consent"><input type="checkbox" name="remove_image" value="yes"><span>Gebruik weer de standaardafbeelding</span></label><?php endif ?>
<label>Beschrijving van de afbeelding<input name="image_alt" maxlength="250" value="<?= e($post['image_alt']) ?>" placeholder="Beschrijf kort wat op de afbeelding staat"></label>
<label>Publicatie<select name="status"><?php foreach(['draft'=>'Bewaren als concept','published'=>'Nu publiceren','scheduled'=>'Publiceren op een gekozen datum','queued'=>'In de wekelijkse wachtrij'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $post['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
<label>Publicatiedatum (alleen bij gekozen datum, Nederlandse tijd)<input type="datetime-local" name="publish_at" value="<?= e($date) ?>"></label>
</div><div class="admin-actions"><button class="button">Artikel opslaan</button><a class="text-link" href="/admin/blog">Terug naar overzicht</a></div></form>

<script src="/assets/blog-editor.js" defer></script>
