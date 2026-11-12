{{-- Campos compartidos por el registro y la edición de documentos --}}
<div class="row g-3">
    <div class="col-md-6">
        <label for="id_tipo_documental" class="form-label">Tipo documental <span class="text-danger">*</span></label>
        <select id="id_tipo_documental" name="id_tipo_documental" required
                @class(['form-select', 'is-invalid' => $errors->has('id_tipo_documental')])>
            @foreach ($tipos as $tipo)
                <option value="{{ $tipo->id_tipo_documental }}" @selected(old('id_tipo_documental', $documento->id_tipo_documental) == $tipo->id_tipo_documental)>{{ $tipo->nombre }}</option>
            @endforeach
        </select>
        @error('id_tipo_documental') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="instancia_emisora" class="form-label">Instancia emisora <span class="text-danger">*</span></label>
        <input type="text" id="instancia_emisora" name="instancia_emisora" value="{{ old('instancia_emisora', $documento->instancia_emisora) }}" maxlength="150" required
               @class(['form-control', 'is-invalid' => $errors->has('instancia_emisora')])>
        @error('instancia_emisora') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-sm-4">
        <label for="numero" class="form-label">Número <span class="text-danger">*</span></label>
        <input type="text" id="numero" name="numero" value="{{ old('numero', $documento->numero) }}" maxlength="30" required placeholder="025/2026"
               @class(['form-control', 'font-monospace', 'is-invalid' => $errors->has('numero')])>
        @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-sm-4">
        <label for="gestion" class="form-label">Gestión <span class="text-danger">*</span></label>
        <input type="number" id="gestion" name="gestion" value="{{ old('gestion', $documento->gestion) }}" min="2000" max="{{ now()->year + 1 }}" required
               @class(['form-control', 'is-invalid' => $errors->has('gestion')])>
        @error('gestion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-sm-4">
        <label for="fecha_emision" class="form-label">Fecha de emisión <span class="text-danger">*</span></label>
        <input type="date" id="fecha_emision" name="fecha_emision" value="{{ old('fecha_emision', $documento->fecha_emision?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required
               @class(['form-control', 'is-invalid' => $errors->has('fecha_emision')])>
        @error('fecha_emision') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
        <input type="text" id="titulo" name="titulo" value="{{ old('titulo', $documento->titulo) }}" maxlength="300" required
               @class(['form-control', 'is-invalid' => $errors->has('titulo')])>
        @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="descripcion" class="form-label">Descripción o resumen</label>
        <textarea id="descripcion" name="descripcion" rows="4" maxlength="5000"
                  @class(['form-control', 'is-invalid' => $errors->has('descripcion')])>{{ old('descripcion', $documento->descripcion) }}</textarea>
        <div class="form-text">Se usa en la búsqueda por palabras clave.</div>
        @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @unless ($documento->exists)
        <div class="col-12">
            <label for="archivo" class="form-label">Archivo PDF <span class="text-danger">*</span></label>
            <input type="file" id="archivo" name="archivo" accept="application/pdf,.pdf" required
                   @class(['form-control', 'is-invalid' => $errors->has('archivo')]) aria-describedby="ayudaArchivo">
            <div id="ayudaArchivo" class="form-text">Solo PDF. Tamaño máximo: {{ \App\Support\LimitesArchivo::pdfMaxEfectivoMb() }} MB.</div>
            @error('archivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endunless
</div>
