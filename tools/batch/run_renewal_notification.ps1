param(
    [string]$date   = (Get-Date -Format yyyy-MM-dd),
    [string]$type   = "renewal",
    [string]$tenant = ""
)

$phpArgs = @("tools/batch/run_renewal_notification.php", "--date=$date", "--type=$type")
if ($tenant -ne "") { $phpArgs += "--tenant=$tenant" }

& "C:\xampp\php\php.exe" $phpArgs
