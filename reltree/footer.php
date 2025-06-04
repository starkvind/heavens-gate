<?php
	$currentPage = basename($_SERVER['SCRIPT_NAME']);
	$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
?>
<div style="width: 100%; text-align: center; color: #888; margin-top:20px;">
	<br />
    <a class="backlink" href="/reltree">← Volver al inicio</a>
	<?php if ($is_admin): ?>
		<?php if ($currentPage !== 'create_character.php'): ?>
			| <a class="backlink" href="/reltree/create_character.php">Crear un personaje</a>
		<?php endif; ?>
		<?php if ($currentPage !== 'view_character_relationship.php'): ?>
			| <a class="backlink" href="/reltree/view_character_relationship.php">Ver relaciones</a>
		<?php endif; ?>
		<?php if ($currentPage !== 'admin_relaciones.php'): ?>
			| <a class="backlink" href="/reltree/admin_relaciones.php">Editar relaciones</a>
		<?php endif; ?>
		<?php if ($currentPage !== 'admin_clanes.php'): ?>
			| <a class="backlink" href="/reltree/admin_clanes.php">Administrar clanes</a>
		<?php endif; ?>
		<?php if ($currentPage !== 'logout.php'): ?>
			| <a class="backlink" href="/reltree/logout.php">Cerrar sesión</a>
		<?php endif; ?>
	<?php endif; ?>
</div>
