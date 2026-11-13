# Abre el .docx en Word, actualiza el índice, lo guarda y lo exporta a PDF (para revisión).
param([string] $Archivo, [string] $Pdf)
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$doc = $word.Documents.Open($Archivo, $false, $false, $false)
if ($doc.TablesOfContents.Count -gt 0) { $doc.TablesOfContents.Item(1).Update() }
$doc.Save()
$doc.ExportAsFixedFormat($Pdf, 17)
$doc.Close($false)
$word.Quit()
