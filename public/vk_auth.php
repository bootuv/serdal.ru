<?php
if (isset($_GET["code"])) {
    echo "<h1>CODE:</h1><h2>" . htmlspecialchars($_GET["code"]) . "</h2><p>Скопируй этот код и отправь в чат.</p>";
} else {
    echo "<h1>Ошибка: код не пришел.</h1>";
    print_r($_GET);
}
?>