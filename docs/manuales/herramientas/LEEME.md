# Regenerar los manuales en Word

Los manuales se mantienen en Markdown (`docs/manuales/*.md`). Para actualizar las versiones en Word
(`docs/manuales/word/`) después de modificarlos:

```powershell
# 1. Instalar la librería docx en una carpeta temporal (no se agrega al proyecto)
mkdir $env:TEMP\gaceta-word; cd $env:TEMP\gaceta-word; npm init -y; npm install docx@9.5.1
Copy-Item C:\SigGacetaUniv\docs\manuales\herramientas\generar-word.js .

# 2. Generar los .docx
$M = "C:\SigGacetaUniv\docs\manuales"
node generar-word.js "$M\MANUAL_USUARIO.md" "$M\word\Manual_de_usuario.docx" "Manual de usuario"
node generar-word.js "$M\MANUAL_TECNICO.md" "$M\word\Manual_tecnico.docx" "Manual técnico"
node generar-word.js "$M\GUIA_INSTALACION_DESPLIEGUE.md" "$M\word\Guia_de_instalacion_y_despliegue.docx" "Guía de instalación y despliegue"

# 3. Actualizar el índice (requiere Microsoft Word); también genera un PDF
foreach ($n in 'Manual_de_usuario','Manual_tecnico','Guia_de_instalacion_y_despliegue') {
  powershell -ExecutionPolicy Bypass -File "$M\herramientas\actualizar-indice-word.ps1" -Archivo "$M\word\$n.docx" -Pdf "$env:TEMP\$n.pdf"
}
```

Si no se ejecuta el paso 3, Word mostrará el índice vacío hasta que se actualice manualmente
(clic derecho sobre el índice → «Actualizar campos»).
