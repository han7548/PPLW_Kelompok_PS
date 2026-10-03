<?php
// this is our own 'bootstrap yeah'
// PERBAIKAN: callback autoload cuma menerima SATU argumen (nama class),
// lalu dicari di setiap subfolder logicgates yang terdaftar.
spl_autoload_register(function (string $class_name) {
    $filename = strtolower($class_name) . ".php";

    foreach (['dbconnection', 'generallogic'] as $subdir) {
        $file = __DIR__ . "/logicgates/" . $subdir . "/" . $filename;
        if (file_exists($file)) {
            include_once $file;
            return;
        }
    }
});