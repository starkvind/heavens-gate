<?php include("sep/main/main_nav_bar.php");	// Barra Navegación ?>
<h2>B&uacute;squeda</h2>
<br/>
<table width="100%">
    <tr>
        <td align="center">
            <form action="index.php" method="get">
                <input type="hidden" name="p" value="busk" />
                Introduce el texto a buscar: <input type="text" name="bsq" maxlength="20" /> 
                Secci&oacute;n:
                <select name="skz">
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