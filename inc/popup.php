<html>
    <head>
        <meta charset="utf-8">
        <title>Codigo Popup</title>
        <link rel="stylesheet" href="../assets/css/estilo.css" type="text/css">
        <script src="//code.jquery.com/jquery-1.12.0.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
        <script>var cerraranuncio = anuncio => {document.getElementById(anuncio).style.display = "none";}</script>
        <script>var pathname = jQuery(location).attr('href');var verificar = pathname.indexOf("miembropress") > -1;jQuery(function() {if (!verificar) {document.getElementById('maximizer').style.display = "block";}});</script>
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
            <div class="modal-dialog-maximizer">
                <div class="modal-content-maximizer">
                    <div class="modal-header-maximizer">
                        <button type="button" onclick="cerraranuncio('maximizer')" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <?php echo $titulo; ?>
                    </div>
                    <div class="modal-body-maximizer">
                        <?php echo $contenido; ?>
                        <?php echo $contenido_link; ?>
                    </div>
                    <div class="modal-footer-maximizer">
                        <button data-dismiss="modal" onclick="cerraranuncio('maximizer')" class="btn-maximizer btn-danger-maximizer">Cerrar</button>
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
    echo "<img style='width: 90%; display: block; margin: 0 auto;' src='../assets/images/ejemplocodigo.jpg' alt='codigo de ejemplo' />";
?>
