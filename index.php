<?php
//include the loader
require_once 'SimpleTemplateEngine/loader.php';

use SimpleTemplateEngine\Environment;

$env = new Environment('examples', '.php');
echo $env->render('home', ['date'=>date('l jS \of F Y')]);
