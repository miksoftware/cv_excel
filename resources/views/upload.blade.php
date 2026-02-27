<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiador de Teléfonos Duplicados</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            text-align: center;
            position: relative;
        }
        .btn-logout {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: 1px solid #ddd;
            color: #888;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            width: auto;
        }
        .btn-logout:hover { border-color: #c33; color: #c33; background: #fff5f5; }
        h1 { color: #333; margin-bottom: 8px; font-size: 1.5rem; }
        p.desc { color: #666; margin-bottom: 24px; font-size: 0.9rem; }
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        .upload-area:hover { border-color: #4a90d9; }
        .upload-area.dragover { border-color: #4a90d9; background: #f0f7ff; }
        .upload-area svg { width: 48px; height: 48px; fill: #999; margin-bottom: 10px; }
        .upload-area span { display: block; color: #666; font-size: 0.9rem; }
        .file-name { color: #4a90d9; font-weight: 600; margin-top: 8px; display: none; }
        input[type="file"] { display: none; }
        button.btn-primary {
            background: #4a90d9;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }
        button.btn-primary:hover { background: #3a7bc8; }
        button.btn-primary:disabled { background: #ccc; cursor: not-allowed; }
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }

        /* Modal oculto para cambiar clave */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 380px;
            width: 90%;
            text-align: center;
        }
        .modal h2 { font-size: 1.2rem; color: #333; margin-bottom: 16px; }
        .modal input[type="password"] {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            margin-bottom: 12px;
            text-align: center;
            transition: border-color 0.3s;
        }
        .modal input[type="password"]:focus { outline: none; border-color: #4a90d9; }
        .modal-buttons { display: flex; gap: 10px; }
        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            transition: background 0.3s;
        }
        .btn-cancel { background: #eee; color: #666; }
        .btn-cancel:hover { background: #ddd; }
        .btn-save { background: #4a90d9; color: white; }
        .btn-save:hover { background: #3a7bc8; }
        .modal-msg {
            font-size: 0.85rem;
            margin-bottom: 12px;
            padding: 8px;
            border-radius: 6px;
            display: none;
        }
        .modal-msg.error-msg { display: block; background: #fee; color: #c33; }
        .modal-msg.success-msg { display: block; background: #efe; color: #3a3; }
    </style>
</head>
<body>
    <div class="card">
        <form action="{{ route('logout') }}" method="POST" style="display:inline">
            @csrf
            <button type="submit" class="btn-logout">Salir</button>
        </form>

        <h1>Limpiador de Duplicados</h1>
        <p class="desc">Sube tu archivo Excel y se descargará un ZIP con 2 archivos: uno sin duplicados (en 0) y otro con el reporte detallado por teléfono.</p>

        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        @endif

        <form action="{{ route('excel.process') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="upload-area" id="dropZone">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM6 20V4h5v7h7v9H6z"/></svg>
                <span>Arrastra tu archivo aquí o haz clic para seleccionar</span>
                <div class="file-name" id="fileName"></div>
            </div>
            <input type="file" name="excel" id="fileInput" accept=".xlsx,.xls">
            <button type="submit" class="btn-primary" id="submitBtn" disabled>Procesar y Descargar</button>
        </form>
    </div>

    <!-- Modal secreto para cambiar clave (F1) -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <h2>🔑 Cambiar Clave</h2>
            <div class="modal-msg" id="modalMsg"></div>
            <input type="password" id="currentPass" placeholder="Clave actual">
            <input type="password" id="newPass" placeholder="Nueva clave">
            <input type="password" id="confirmPass" placeholder="Confirmar nueva clave">
            <div class="modal-buttons">
                <button class="btn-cancel" onclick="closeModal()">Cancelar</button>
                <button class="btn-save" onclick="savePassword()">Guardar</button>
            </div>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileName = document.getElementById('fileName');
        const submitBtn = document.getElementById('submitBtn');

        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileName();
            }
        });
        fileInput.addEventListener('change', updateFileName);
        function updateFileName() {
            if (fileInput.files.length) {
                fileName.textContent = fileInput.files[0].name;
                fileName.style.display = 'block';
                submitBtn.disabled = false;
            }
        }

        // Modal secreto con F1
        const modalOverlay = document.getElementById('modalOverlay');
        const modalMsg = document.getElementById('modalMsg');

        document.addEventListener('keydown', (e) => {
            if (e.key === 'F1') {
                e.preventDefault();
                modalOverlay.classList.add('active');
                document.getElementById('currentPass').focus();
            }
            if (e.key === 'Escape') closeModal();
        });

        function closeModal() {
            modalOverlay.classList.remove('active');
            document.getElementById('currentPass').value = '';
            document.getElementById('newPass').value = '';
            document.getElementById('confirmPass').value = '';
            modalMsg.className = 'modal-msg';
            modalMsg.textContent = '';
        }

        function savePassword() {
            const current = document.getElementById('currentPass').value;
            const newPass = document.getElementById('newPass').value;
            const confirm = document.getElementById('confirmPass').value;

            if (!current || !newPass || !confirm) {
                showMsg('Completa todos los campos.', 'error');
                return;
            }
            if (newPass !== confirm) {
                showMsg('Las claves nuevas no coinciden.', 'error');
                return;
            }
            if (newPass.length < 4) {
                showMsg('La nueva clave debe tener al menos 4 caracteres.', 'error');
                return;
            }

            fetch('{{ route("change.password") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ current_password: current, new_password: newPass })
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (ok) {
                    showMsg('Clave cambiada correctamente.', 'success');
                    setTimeout(closeModal, 1500);
                } else {
                    showMsg(data.error || 'Error al cambiar la clave.', 'error');
                }
            })
            .catch(() => showMsg('Error de conexión.', 'error'));
        }

        function showMsg(text, type) {
            modalMsg.textContent = text;
            modalMsg.className = 'modal-msg ' + (type === 'error' ? 'error-msg' : 'success-msg');
        }
    </script>
</body>
</html>
