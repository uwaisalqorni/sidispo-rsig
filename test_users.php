<?php
$conn = new mysqli('localhost', 'root', 'bismillah', 'sidispo');
$hash = '$2y$10$i/zwnL6pL4siPeUZzeac7exAKe/BQcpHLbb9DGGFghqS09iaE7NBK';
$conn->query("UPDATE users SET password_hash='$hash' WHERE email='keuangan@gmail.com'");
echo "Updated password for keuangan@gmail.com to 123456\n";
