// Convierte los manuales Markdown del proyecto a Word (.docx) con formato institucional.
// Uso: node generar.js <entrada.md> <salida.docx> "<título corto para el encabezado>"
const fs = require('fs');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, Table, TableRow, TableCell,
  WidthType, BorderStyle, ShadingType, LevelFormat, Header, Footer, PageNumber, TableOfContents,
  PageBreak, ExternalHyperlink, TabStopType,
} = require('docx');

const [, , entrada, salida, tituloCorto] = process.argv;
const md = fs.readFileSync(entrada, 'utf8').replace(/\r\n/g, '\n');
// Une las líneas que en Markdown forman un mismo párrafo, punto de lista o nota (saltos de línea "suaves").
function unirLineas(crudas) {
  const resultado = [];
  let enCodigo = false;
  const esBloque = (l) => /^\s*(#|\||```|>|[-*] |\d+\. |---\s*$)/.test(l) || l.trim() === '';
  for (const linea of crudas) {
    if (linea.trim().startsWith('```')) { enCodigo = !enCodigo; resultado.push(linea); continue; }
    if (enCodigo) { resultado.push(linea); continue; }
    const anterior = resultado.length ? resultado[resultado.length - 1] : '';
    const anteriorUnible = anterior.trim() !== '' && !/^\s*(#|\||```|---\s*$)/.test(anterior);
    if (linea.trim().startsWith('>') && anterior.trim().startsWith('>')) {
      resultado[resultado.length - 1] = anterior + ' ' + linea.trim().replace(/^>\s?/, '');
    } else if (!esBloque(linea) && anteriorUnible) {
      resultado[resultado.length - 1] = anterior + ' ' + linea.trim();
    } else {
      resultado.push(linea);
    }
  }
  return resultado;
}

const lineas = unirLineas(md.split('\n'));

const AZUL = '0B3D6B';
const DORADO = 'C9A227';
const GRIS_TABLA = 'D9E2EC';
const ANCHO = 9638; // A4 con márgenes de 2 cm (DXA)
const FUENTE = 'Calibri';
const MONO = 'Consolas';

// ---------------------------------------------------------------- Texto en línea
function enLinea(texto, base = {}) {
  const runs = [];
  const patron = /(\*\*[^*]+\*\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/g;
  let ultimo = 0;
  let m;
  while ((m = patron.exec(texto)) !== null) {
    if (m.index > ultimo) runs.push(new TextRun({ text: texto.slice(ultimo, m.index), ...base }));
    const t = m[0];
    if (t.startsWith('**')) {
      runs.push(...enLinea(t.slice(2, -2), { ...base, bold: true }));
    } else if (t.startsWith('`')) {
      runs.push(new TextRun({ text: t.slice(1, -1), ...base, font: MONO, size: (base.size || 22) - 2, color: '7A2E00' }));
    } else {
      const [, etiqueta, url] = t.match(/\[([^\]]+)\]\(([^)]+)\)/);
      if (/^https?:/.test(url)) {
        runs.push(new ExternalHyperlink({ link: url, children: [new TextRun({ text: etiqueta, ...base, style: 'Hyperlink' })] }));
      } else {
        runs.push(new TextRun({ text: etiqueta, ...base, bold: true }));
      }
    }
    ultimo = m.index + t.length;
  }
  if (ultimo < texto.length) runs.push(new TextRun({ text: texto.slice(ultimo), ...base }));
  return runs.length ? runs : [new TextRun({ text: '', ...base })];
}

// ---------------------------------------------------------------- Tablas
function filaTabla(linea) {
  return linea.trim().replace(/^\||\|$/g, '').split('|').map((c) => c.trim());
}

function tabla(filas) {
  const encabezado = filas[0];
  const cuerpo = filas.slice(2); // la segunda fila es el separador |---|
  const columnas = encabezado.length;
  // Anchos proporcionales al contenido (con mínimo y máximo razonables).
  const largos = encabezado.map((_, i) => {
    const max = Math.max(...[encabezado, ...cuerpo].map((f) => (f[i] || '').replace(/[*`]/g, '').length));
    return Math.min(Math.max(max, 6), 60);
  });
  const suma = largos.reduce((a, b) => a + b, 0);
  const anchos = largos.map((l) => Math.floor((l / suma) * ANCHO));
  anchos[anchos.length - 1] += ANCHO - anchos.reduce((a, b) => a + b, 0);

  const borde = { style: BorderStyle.SINGLE, size: 4, color: 'A0AEC0' };
  const bordes = { top: borde, bottom: borde, left: borde, right: borde };

  const celda = (texto, i, esEncabezado) => new TableCell({
    width: { size: anchos[i], type: WidthType.DXA },
    borders: bordes,
    margins: { top: 60, bottom: 60, left: 90, right: 90 },
    shading: esEncabezado ? { type: ShadingType.CLEAR, color: 'auto', fill: GRIS_TABLA } : undefined,
    children: texto.split(/<br\s*\/?>/i).map((parte) => new Paragraph({
      spacing: { before: 0, after: 0 },
      children: enLinea(parte, { size: 18, bold: esEncabezado || undefined }),
    })),
  });

  return new Table({
    width: { size: ANCHO, type: WidthType.DXA },
    columnWidths: anchos,
    rows: [
      new TableRow({ tableHeader: true, children: encabezado.map((t, i) => celda(t, i, true)) }),
      ...cuerpo.map((f) => new TableRow({ children: Array.from({ length: columnas }, (_, i) => celda(f[i] || '', i, false)) })),
    ],
  });
}

// ---------------------------------------------------------------- Documento
const titulo = (lineas.find((l) => l.startsWith('# ')) || '# Manual').slice(2).trim();
const subtitulo = (lineas.find((l) => /^\*\*.+\*\*$/.test(l.trim())) || '').replace(/\*\*/g, '').trim();
const version = lineas.find((l) => l.startsWith('Versión')) || '';

const cuerpo = [];
let instanciaLista = 0;
let enLista = false;
let saltarContenido = false;

for (let i = 0; i < lineas.length; i++) {
  const linea = lineas[i];
  const recortada = linea.trim();

  // Encabezado del archivo (título, subtítulo, versión): va en la portada.
  if (linea.startsWith('# ') || recortada === `**${subtitulo}**` || linea === version) continue;

  // El índice manual de Markdown se reemplaza por el índice automático de Word.
  if (/^## Contenido/.test(linea)) { saltarContenido = true; continue; }
  if (saltarContenido) {
    if (linea.startsWith('## ')) saltarContenido = false; else continue;
  }

  if (recortada === '---' || recortada === '') { if (recortada === '') enLista = false; continue; }

  // Bloque de código
  if (recortada.startsWith('```')) {
    const codigo = [];
    i++;
    while (i < lineas.length && !lineas[i].trim().startsWith('```')) codigo.push(lineas[i++]);
    codigo.forEach((c, n) => cuerpo.push(new Paragraph({
      shading: { type: ShadingType.CLEAR, color: 'auto', fill: 'F3F5F8' },
      border: {
        left: { style: BorderStyle.SINGLE, size: 12, color: AZUL, space: 6 },
        ...(n === 0 ? { top: { style: BorderStyle.SINGLE, size: 2, color: 'F3F5F8', space: 4 } } : {}),
        ...(n === codigo.length - 1 ? { bottom: { style: BorderStyle.SINGLE, size: 2, color: 'F3F5F8', space: 4 } } : {}),
      },
      spacing: { before: 0, after: n === codigo.length - 1 ? 160 : 0, line: 240 },
      indent: { left: 120 },
      children: [new TextRun({ text: c.length ? c : ' ', font: MONO, size: 16 })],
    })));
    continue;
  }

  // Tabla
  if (recortada.startsWith('|')) {
    const filas = [];
    while (i < lineas.length && lineas[i].trim().startsWith('|')) filas.push(filaTabla(lineas[i++]));
    i--;
    cuerpo.push(tabla(filas));
    cuerpo.push(new Paragraph({ spacing: { before: 0, after: 120 }, children: [] }));
    continue;
  }

  // Títulos
  const h = linea.match(/^(#{2,4}) (.+)/);
  if (h) {
    const nivel = { 2: HeadingLevel.HEADING_1, 3: HeadingLevel.HEADING_2, 4: HeadingLevel.HEADING_3 }[h[1].length];
    cuerpo.push(new Paragraph({ heading: nivel, children: enLinea(h[2].trim()) }));
    continue;
  }

  // Cita / nota
  if (recortada.startsWith('>')) {
    cuerpo.push(new Paragraph({
      shading: { type: ShadingType.CLEAR, color: 'auto', fill: 'FFF8E1' },
      border: { left: { style: BorderStyle.SINGLE, size: 18, color: DORADO, space: 8 } },
      indent: { left: 160 },
      spacing: { before: 60, after: 120 },
      children: enLinea(recortada.replace(/^>\s?/, ''), { italics: true }),
    }));
    continue;
  }

  // Listas (con viñetas, numeradas y de verificación)
  const lista = linea.match(/^(\s*)([-*]|\d+\.) (.+)/);
  if (lista) {
    const nivel = Math.min(Math.floor(lista[1].length / 3), 2);
    const numerada = /\d+\./.test(lista[2]);
    let texto = lista[3];
    if (!enLista && numerada) instanciaLista++;
    enLista = true;
    const verificacion = texto.match(/^\[( |x)\] (.+)/);
    if (verificacion) texto = `${verificacion[1] === 'x' ? '☑' : '☐'} ${verificacion[2]}`;
    // Las listas numeradas usan el número escrito en el manual: así se conserva la secuencia
    // aunque la lista esté interrumpida por una tabla o un bloque de código (1, 2, tabla, 3…).
    cuerpo.push(new Paragraph({
      numbering: numerada || verificacion ? undefined : { reference: 'vinetas', level: nivel },
      indent: numerada ? { left: 420 + nivel * 360, hanging: 300 } : verificacion ? { left: 360 } : undefined,
      spacing: { before: 20, after: 40 },
      children: numerada ? [new TextRun({ text: `${lista[2]} `, bold: true, color: AZUL }), ...enLinea(texto)] : enLinea(texto),
    }));
    continue;
  }

  // Párrafo normal
  enLista = false;
  cuerpo.push(new Paragraph({ spacing: { after: 120 }, children: enLinea(recortada) }));
}

// ---------------------------------------------------------------- Portada e índice
const portada = [
  new Paragraph({ spacing: { before: 1800, after: 120 }, alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: 'UNIVERSIDAD PÚBLICA DE EL ALTO', bold: true, size: 28, color: AZUL })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 1200 },
    border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: DORADO, space: 8 } },
    children: [new TextRun({ text: 'Gaceta Universitaria', size: 26, color: '555555' })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 240 },
    children: [new TextRun({ text: titulo, bold: true, size: 56, color: AZUL })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 2400 },
    children: [new TextRun({ text: subtitulo.replace(/ — .*/, ''), size: 26, color: '333333' })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 },
    children: [new TextRun({ text: version, size: 22, color: '555555' })] }),
  new Paragraph({ alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: 'El Alto — Bolivia', size: 22, color: '555555' })] }),
  new Paragraph({ children: [new PageBreak()] }),
  new Paragraph({ spacing: { after: 200 }, border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: DORADO, space: 4 } },
    children: [new TextRun({ text: 'Contenido', bold: true, size: 32, color: AZUL })] }),
  new TableOfContents('Contenido', { hyperlink: true, headingStyleRange: '1-2' }),
  new Paragraph({ children: [new PageBreak()] }),
];

const doc = new Document({
  creator: 'Gaceta Universitaria UPEA',
  title: `${titulo} — Gaceta Universitaria UPEA`,
  styles: {
    default: { document: { run: { font: FUENTE, size: 22 } } },
    paragraphStyles: [
      { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { font: FUENTE, size: 32, bold: true, color: AZUL },
        paragraph: { spacing: { before: 360, after: 160 }, keepNext: true, outlineLevel: 0,
          border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: DORADO, space: 4 } } } },
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { font: FUENTE, size: 26, bold: true, color: AZUL },
        paragraph: { spacing: { before: 240, after: 100 }, keepNext: true, outlineLevel: 1 } },
      { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { font: FUENTE, size: 23, bold: true, color: '333333' },
        paragraph: { spacing: { before: 200, after: 80 }, keepNext: true, outlineLevel: 2 } },
    ],
  },
  numbering: {
    config: [
      { reference: 'vinetas', levels: [0, 1, 2].map((n) => ({ level: n, format: LevelFormat.BULLET, text: ['•', '◦', '▪'][n],
        alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 360 + n * 360, hanging: 240 } } } })) },
      { reference: 'numerada', levels: [0, 1, 2].map((n) => ({ level: n, format: [LevelFormat.DECIMAL, LevelFormat.LOWER_LETTER, LevelFormat.LOWER_ROMAN][n],
        text: `%${n + 1}.`, alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400 + n * 360, hanging: 300 } } } })) },
    ],
  },
  sections: [{
    properties: {
      page: { margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } },
      titlePage: true,
    },
    headers: {
      default: new Header({ children: [new Paragraph({
        tabStops: [{ type: TabStopType.RIGHT, position: ANCHO }],
        border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: 'A0AEC0', space: 4 } },
        children: [
          new TextRun({ text: 'Gaceta Universitaria — UPEA', size: 16, color: '777777' }),
          new TextRun({ text: `\t${tituloCorto}`, size: 16, color: '777777' }),
        ] })] }),
      first: new Header({ children: [new Paragraph({ children: [] })] }),
    },
    footers: {
      default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [
        new TextRun({ children: ['Página ', PageNumber.CURRENT, ' de ', PageNumber.TOTAL_PAGES], size: 16, color: '777777' }),
      ] })] }),
      first: new Footer({ children: [new Paragraph({ children: [] })] }),
    },
    children: [...portada, ...cuerpo],
  }],
});

Packer.toBuffer(doc).then((buffer) => {
  fs.writeFileSync(salida, buffer);
  console.log(`OK ${salida} (${(buffer.length / 1024).toFixed(0)} KB, ${cuerpo.length} bloques)`);
});
