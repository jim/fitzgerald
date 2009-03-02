<?php
    // See the accompanying README for how to use Fitzgerald!

    include('lib/fitzgerald.php');

    class Application extends Fitzgerald {
        // Define your controller methods, remembering to return a value for the browser!
    }

    $app = new Application();

    // Define your url mappings. TAke advantage of placeholders and regexes for safety.

    $app->run();
?>