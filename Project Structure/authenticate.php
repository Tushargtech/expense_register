<?php

$userModel = new User($dbConfig);
$authController = new AuthController($userModel);
$authController->login();
