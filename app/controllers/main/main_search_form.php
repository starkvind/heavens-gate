<?php setMetaFromPage("Busqueda | Heaven's Gate", "Buscador de contenido de la campana.", null, 'website'); ?>
<?php include("app/partials/main_nav_bar.php");	// Barra Navegación ?>
<h2>B&uacute;squeda</h2>
<br/>
<table width="100%">
    <tr>
        <td align="center">
            <form action="/search/results" method="get">
                Introduce el texto a buscar: <input type="text" name="q" maxlength="20" /> 
                Secci&oacute;n:
                <select name="section">
                    <option value="biografias">Biograf&iacute;as</option>
                    <option value="escritos">Documentos</option>
                    <option value="objetos">Inventario</option>
                    <option value="sistemas">Sistemas</option>
                    <option value="habilidades">Habilidades</option>
                    <option value="merydef">M&eacute;ritos y Defectos</option>
                    <option value="dones">Dones</option>
                </select>
                <br/><br/><br/>
                <input type="submit" value="Buscar" class="boton1" />
            </form>
        </td>
    </tr>
</table>
