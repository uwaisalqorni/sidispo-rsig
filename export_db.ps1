# ============================================================================
# Script Export & Sanitasi Database SiDispo ke sidispo.sql
# ============================================================================

$ErrorActionPreference = "Stop"

$dumpPath = "C:\Program Files\MariaDB 10.4\bin\mysqldump.exe"
if (-not (Test-Path $dumpPath)) {
    $dumpPath = "C:\xampp\mysql\bin\mysqldump.exe"
}
if (-not (Test-Path $dumpPath)) {
    Write-Error "mysqldump.exe tidak ditemukan di C:\Program Files\MariaDB 10.4\bin ataupun C:\xampp\mysql\bin"
    exit 1
}

$dbUser = "root"
$dbPass = "bismillah"
$dbName = "sidispo"
$outputFile = Join-Path $PSScriptRoot "sidispo.sql"

Write-Host ">>> Memulai export database '$dbName'..." -ForegroundColor Cyan

# 1. Export Struktur / Schema tanpa data
Write-Host "1/4 Exporting schema & structure (routines, triggers, events)..."
$schemaOutput = & $dumpPath "--user=$dbUser" "--password=$dbPass" --routines --triggers --events --add-drop-table --no-data $dbName 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error "Gagal export schema: $schemaOutput"
    exit 1
}

# 2. Export Master / Reference Data
Write-Host "2/4 Exporting master reference tables..."
$masterTables = @("app_settings", "folders", "jabatan_hierarki", "master_asal_surat", "master_jabatan", "master_perihal")
$dataOutput = & $dumpPath "--user=$dbUser" "--password=$dbPass" --no-create-info --skip-triggers $dbName $masterTables 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error "Gagal export master data: $dataOutput"
    exit 1
}

# 3. Sanitasi Data Sensitif
Write-Host "3/4 Mensanitasi data sensitif (SMTP credentials, passwords, dll)..."

$schemaString = $schemaOutput -join "`r`n"
$splitMarker = "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;"

if ($schemaString.Contains($splitMarker)) {
    $splitIdx = $schemaString.IndexOf($splitMarker)
    $schemaBody = $schemaString.Substring(0, $splitIdx)
    $schemaFooter = $schemaString.Substring($splitIdx)
} else {
    $schemaBody = $schemaString
    $schemaFooter = @"
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
"@
}

# Filter out comments and system configs from dataOutput, keep table inserts
$dataLines = $dataOutput | Where-Object {
    $_ -notmatch "^/\*!" -and
    $_ -notmatch "^--" -and
    $_ -notmatch "^\s*$"
}

$sanitizedData = ($dataLines -join "`r`n")

# Sanitasi app_settings SMTP credentials & data kontak
$sanitizedData = $sanitizedData -replace "\(15,'smtp_user',.*?\)", "(15,'smtp_user','your_email@gmail.com','2026-09-03 03:21:04')"
$sanitizedData = $sanitizedData -replace "\(16,'smtp_pass',.*?\)", "(16,'smtp_pass','your_app_password_here','2026-09-03 03:21:04')"
$sanitizedData = $sanitizedData -replace "\(3,'telp_rs',.*?\)", "(3,'telp_rs','08xxxxxxxxxx','2026-03-03 02:43:12')"
$sanitizedData = $sanitizedData -replace "\(4,'email_rs',.*?\)", "(4,'email_rs','admin@example.com','2026-05-07 02:22:39')"

# Default Admin User (password: password123, hash bcrypt)
$adminSeed = @'

--
-- Sample admin user (password: password123 -- CHANGE AFTER DEPLOY)
-- password_hash = bcrypt('password123')
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`,`nip`,`nama_lengkap`,`email`,`no_hp`,`password_hash`,`jabatan`,`jabatan_id`,`unit`,`role`,`foto_profil`,`is_active`,`last_login_at`,`created_at`,`updated_at`) VALUES
  (1,'000000000000','Administrator','admin@sidispo.local',NULL,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator',6,NULL,'ADMIN',NULL,1,NULL,NOW(),NOW());
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
'@

# 4. Gabungkan File Final
Write-Host "4/4 Menyimpan hasil ke $outputFile..."

$banner = @"
-- ============================================================================
-- SEED DATA (sanitized - sensitive data replaced with placeholders)
-- ============================================================================
"@

$divider = @"
-- ============================================================================
"@

$finalSql = $schemaBody.TrimEnd() + "`r`n`r`n" + $banner + "`r`n`r`n" + $sanitizedData.Trim() + "`r`n" + $adminSeed + "`r`n`r`n" + $divider + "`r`n`r`n" + $schemaFooter.Trim() + "`r`n`r`n-- Dump completed`r`n"

[System.IO.File]::WriteAllText($outputFile, $finalSql, [System.Text.Encoding]::UTF8)

$size = [Math]::Round((Get-Item $outputFile).Length / 1KB, 2)
Write-Host ">>> Berhasil! File $outputFile ($size KB) siap dipush ke GitHub." -ForegroundColor Green
