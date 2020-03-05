<html>
    <head>
        <meta charset="utf-8">
        <title>Codigo Popup</title>
        <link rel="stylesheet" href="css/estilo.css" type="text/css">
        <script src="//code.jquery.com/jquery-1.12.0.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
        <script>var cerraranuncio = anuncio => {document.getElementById(anuncio).style.display = "none";}</script>
        <script>var pathname = jQuery(location).attr('href');var verificar = pathname.indexOf("genius") > -1;jQuery(function() {if (!verificar) {document.getElementById('maximizer').style.display = "block";}});</script>
    </head>
    <body>
        <?php
        $posicion = $_GET['posicion'];
        $titulo = $_GET['title-popup'];
        $titulo_html = $_GET['title-html'];
        $titulo_color = $_GET['title-color'];
        $contenido = $_GET['content-popup'];
        $contenido_html = $_GET['content-html'];
        $contenido_color = $_GET['content-color'];
        $contenido_link = $_GET['content-link'];
        $text_link = $_GET['text-link'];

        if (empty($titulo)) {
            if (!empty($titulo_html)) {
                $titulo = $titulo_html;
            }else {
                $titulo = "<h3>Titulo vacio</h3>";
            }
        }else{
            $titulo = '<h3 style="color:'.$titulo_color.'">'."$titulo".'</h3>';
        }

        if (empty($contenido)) {
            if (!empty($contenido_html)) {
                $contenido = $contenido_html;
            }else {
                $contenido = "<h3>Contenido vacio</h3>";
            }
        }else{
            $contenido = '<h4 style="color:'.$contenido_color.'">'."$contenido".'</h4>';
        }

        if (!empty($contenido_link)) {
            if (!empty($text_link)) {
                $contenido_link = "<a target='_blank' href='".$contenido_link."'>".$text_link."</a>";
            }else {
                $contenido_link = "<a target='_blank' href='".$contenido_link."'>".$contenido_link."</a>";
            }
        }

        ?>
        <div class="ventana_flotante <?php echo $posicion; ?>" id="maximizer">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" onclick="cerraranuncio('maximizer')" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <?php echo $titulo; ?>
                    </div>
                    <div class="modal-body">
                        <?php echo $contenido; ?>
                        <?php echo $contenido_link; ?>
                    </div>
                    <div class="modal-footer">
                        <button data-dismiss="modal" onclick="cerraranuncio('maximizer')" class="btn btn-danger">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>

<?php
    echo "<h2>This is a popup preview. Close It and follow the instructions below:</h2>";
    echo "<h2>Press CTRL + U and then copy the popup code</h2>";
    echo "<h3>Example</h3>";
    echo "<img style='width: 90%; display: block; margin: 0 auto;' src='images/ejemplocodigo.jpg' alt='codigo de ejemplo' />";
?>
