<?php

    if ( ! defined( 'ABSPATH' ) ) die();

?>

<h1>Options.</h1>
<form action="options.php" method="post">
    <?php
        settings_fields( 'ph-hotmart-group' );
        do_settings_sections( 'ph-hotmart-page' );
        submit_button();
    ?>
</form>