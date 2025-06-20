<?php 
// $hash = "$2y$10\$gYb8oBHQJ2d1jfPwZfEUe.1O4iAfjmGyXmi16Mby394GiggODjOWa";
echo "Password hash: " . password_hash("1", PASSWORD_DEFAULT) . "\n";
// echo "hash true ? " . password_verify("1111",$hash) . "\n";