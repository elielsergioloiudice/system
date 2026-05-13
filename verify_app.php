<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

$jsonFile = __DIR__ . '/verify_app.json';



// ======================================================
// CREAR JSON SI NO EXISTE
// ======================================================

if (!file_exists($jsonFile)) {

    $default = [

        "panel_config" => [

            "admin_code" => "1125",

            "urls_disponibles" => [
                "https://miargentina.esl.ar/local/"
            ]
        ],

        "records" => []
    ];

    file_put_contents(

        $jsonFile,

        json_encode(

            $default,

            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
    );
}



// ======================================================
// FUNCIONES
// ======================================================

function cargarJSON($jsonFile){

    $contenido = @file_get_contents($jsonFile);

    $data = json_decode($contenido, true);

    if(!is_array($data)){

        return [

            "panel_config" => [

                "admin_code" => "1125",

                "urls_disponibles" => []
            ],

            "records" => []
        ];
    }

    if(!isset($data['panel_config'])){

        $data['panel_config'] = [

            "admin_code" => "1125",

            "urls_disponibles" => []
        ];
    }

    if(!isset($data['records'])){

        $data['records'] = [];
    }

    return $data;
}



function guardarJSON($jsonFile, $data){

    file_put_contents(

        $jsonFile,

        json_encode(

            $data,

            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )
    );
}



function responderJSON($array){

    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode(

        $array,

        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit();
}



function generarToken($cuil){

    $hash = hash(
        'sha256',
        'verify_app_' . $cuil . '_2026'
    );

    $hash = strtoupper($hash);

    $hash = preg_replace('/[^A-Z0-9]/', '', $hash);

    return substr($hash, 0, 20);
}



function fechaActual(){

    return date('Y-m-d H:i:s');
}



function fechaVencimientoDefault(){

    return date(
        'Y-m-d H:i:s',
        strtotime('+30 days')
    );
}



$data = cargarJSON($jsonFile);



// ======================================================
// API AJAX
// ======================================================

if(isset($_GET['action'])){

    $action = trim($_GET['action']);



    // ==================================================
    // LISTAR
    // ==================================================

    if($action === 'list'){

        responderJSON([

            "status" => "success",

            "records" => $data['records'],

            "urls" =>
                $data['panel_config']['urls_disponibles']
        ]);
    }



    // ==================================================
    // CREAR REGISTRO
    // ==================================================

    if($action === 'create_record'){

        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        $cuil = preg_replace(
            '/\D/',
            '',
            $input['cuil'] ?? ''
        );

        $url = trim(
            $input['url_sistema'] ?? ''
        );

        $vencimiento = trim(
            $input['vencimiento_token'] ?? ''
        );

        if(strlen($cuil) !== 11){

            responderJSON([

                "status" => "error",

                "message" => "CUIL inválido"
            ]);
        }

        if(empty($url)){

            responderJSON([

                "status" => "error",

                "message" => "URL inválida"
            ]);
        }

        $token = generarToken($cuil);

        foreach($data['records'] as $record){

            if(
                ($record['token'] ?? '')
                === $token
            ){

                responderJSON([

                    "status" => "error",

                    "message" => "Registro existente"
                ]);
            }
        }

        if(empty($vencimiento)){

            $vencimiento =
                fechaVencimientoDefault();
        }

        $nuevoRegistro = [

            "id" => uniqid('reg_'),

            "cuil" => $cuil,

            "token" => $token,

            "url_sistema" => $url,

            "fecha_creacion" => fechaActual(),

            "vencimiento_token" => $vencimiento,

            "suspender_token" => false
        ];

        $data['records'][] = $nuevoRegistro;

        guardarJSON($jsonFile, $data);

        responderJSON([

            "status" => "success"
        ]);
    }

    // ==================================================
    // CARGA MASIVA RECORDS (IMPORTACIÓN USERS.JSON)
    // ==================================================
    if($action === 'create_massive_records'){
        $input = json_decode(file_get_contents("php://input"), true);
        $records = $input['records'] ?? [];
        $count = 0;

        foreach($records as $rec){
            $cuil = preg_replace('/\D/', '', $rec['cuil'] ?? '');
            $url = trim($rec['url_sistema'] ?? '');
            $venc = trim($rec['vencimiento_token'] ?? '');
            $suspendido = (bool)($rec['suspender_token'] ?? false);
            $fechaCreacion = trim($rec['fecha_creacion'] ?? fechaActual());
            
            if(strlen($cuil) !== 11 || empty($url)) continue;
            
            $token = generarToken($cuil);
            
            // Evitar duplicados
            $existe = false;
            foreach($data['records'] as $r){
                if(($r['token'] ?? '') === $token){ $existe = true; break; }
            }
            if($existe) continue;

            $data['records'][] = [
                "id" => uniqid('reg_'),
                "cuil" => $cuil,
                "token" => $token,
                "url_sistema" => $url,
                "fecha_creacion" => $fechaCreacion,
                "vencimiento_token" => $venc ?: fechaVencimientoDefault(),
                "suspender_token" => $suspendido
            ];
            $count++;
        }
        guardarJSON($jsonFile, $data);
        responderJSON(["status" => "success", "added" => $count]);
    }


    // ==================================================
    // ACTUALIZAR REGISTRO
    // ==================================================

    if($action === 'update_record'){

        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        $id = trim($input['id'] ?? '');

        $url = trim($input['url_sistema'] ?? '');

        $vencimiento = trim($input['vencimiento_token'] ?? '');

        $suspendido = (bool)($input['suspender_token'] ?? false);

        if(empty($id)){

            responderJSON([

                "status" => "error",

                "message" => "ID inválido"
            ]);
        }

        if(empty($url)){

            responderJSON([

                "status" => "error",

                "message" => "URL inválida"
            ]);
        }

        if(empty($vencimiento)){

            responderJSON([

                "status" => "error",

                "message" => "Vencimiento inválido"
            ]);
        }

        foreach($data['records'] as $index => $record){

            if(
                ($record['id'] ?? '')
                === $id
            ){

                $data['records'][$index]['url_sistema']
                    = $url;

                $data['records'][$index]['vencimiento_token']
                    = $vencimiento;

                $data['records'][$index]['suspender_token']
                    = $suspendido;

                guardarJSON($jsonFile, $data);

                responderJSON([

                    "status" => "success"
                ]);
            }
        }

        responderJSON([

            "status" => "error",

            "message" => "Registro no encontrado"
        ]);
    }



    // ==================================================
    // ELIMINAR REGISTRO
    // ==================================================

    if($action === 'delete_record'){

        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        $id = trim($input['id'] ?? '');

        foreach($data['records'] as $index => $record){

            if(
                ($record['id'] ?? '')
                === $id
            ){

                unset($data['records'][$index]);

                $data['records'] =
                    array_values($data['records']);

                guardarJSON($jsonFile, $data);

                responderJSON([

                    "status" => "success"
                ]);
            }
        }

        responderJSON([

            "status" => "error",

            "message" => "Registro no encontrado"
        ]);
    }



    // ==================================================
    // AGREGAR URL
    // ==================================================

    if($action === 'add_url'){

        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        $url = trim($input['url'] ?? '');

        if(empty($url)){

            responderJSON([

                "status" => "error",

                "message" => "URL inválida"
            ]);
        }

        if(
            !in_array(
                $url,
                $data['panel_config']['urls_disponibles']
            )
        ){

            $data['panel_config']['urls_disponibles'][] =
                $url;

            guardarJSON($jsonFile, $data);
        }

        responderJSON([

            "status" => "success"
        ]);
    }



    // ==================================================
    // MODIFICAR URL
    // ==================================================

    if($action === 'update_url'){

        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        $old = trim($input['old'] ?? '');

        $new = trim($input['new'] ?? '');

        if(empty($new)){

            responderJSON([

                "status" => "error",

                "message" => "URL inválida"
            ]);
        }

        foreach(
            $data['panel_config']['urls_disponibles']
            as $index => $url
        ){

            if($url === $old){

                $data['panel_config']['urls_disponibles'][$index]
                    = $new;
            }
        }

        foreach(
            $data['records']
            as $index => $record
        ){

            if(
                $record['url_sistema'] === $old
            ){

                $data['records'][$index]['url_sistema']
                    = $new;
            }
        }

        guardarJSON($jsonFile, $data);

        responderJSON([

            "status" => "success"
        ]);
    }



    // ==================================================
    // ELIMINAR URL
    // ==================================================

    if($action === 'delete_url'){

        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        $url = trim($input['url'] ?? '');

        $data['panel_config']['urls_disponibles'] =
            array_values(
                array_filter(

                    $data['panel_config']['urls_disponibles'],

                    function($u) use ($url){

                        return $u !== $url;
                    }
                )
            );

        guardarJSON($jsonFile, $data);

        responderJSON([

            "status" => "success"
        ]);
    }



    // ==================================================
    // CAMBIO MASIVO URL
    // ==================================================

    if($action === 'massive_url_change'){

        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        $url = trim($input['url'] ?? '');

        if(empty($url)){

            responderJSON([

                "status" => "error",

                "message" => "URL inválida"
            ]);
        }

        foreach($data['records'] as $index => $record){

            $data['records'][$index]['url_sistema']
                = $url;
        }

        guardarJSON($jsonFile, $data);

        responderJSON([

            "status" => "success"
        ]);
    }



    responderJSON([

        "status" => "error",

        "message" => "Acción inválida"
    ]);
}



// ======================================================
// VALIDACION APP
// ======================================================

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $token = trim($_POST['token'] ?? '');

    if(empty($token)){

        http_response_code(400);

        responderJSON([

            "status" => "error",

            "message" => "Token faltante"
        ]);
    }

    $registroEncontrado = null;

    foreach($data['records'] as $record){

        if(
            ($record['token'] ?? '')
            === $token
        ){

            $registroEncontrado = $record;

            break;
        }
    }

    if(!$registroEncontrado){

        http_response_code(403);

        responderJSON([

            "status" => "error",

            "message" => "Token inválido"
        ]);
    }

    if(
        ($registroEncontrado['suspender_token'] ?? false)
        === true
    ){

        http_response_code(403);

        responderJSON([

            "status" => "error",

            "message" => "Token suspendido"
        ]);
    }

    $fechaActual = time();

    $fechaVencimiento = strtotime(
        $registroEncontrado['vencimiento_token']
    );

    if($fechaActual > $fechaVencimiento){

        http_response_code(403);

        responderJSON([

            "status" => "error",

            "message" => "Token vencido"
        ]);
    }

    responderJSON([

        "status" => "success",

        "target_url" =>
            $registroEncontrado['url_sistema']
    ]);
}



// ======================================================
// PANEL ADMIN
// ======================================================

$adminCode =
    $data['panel_config']['admin_code'];

?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>verify_app</title>

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    padding:10px;
    background:#111827;
    color:white;
    font-family:Arial;
}

.box{
    background:#1f2937;
    border-radius:14px;
    padding:14px;
    margin-bottom:12px;
}

input,
select,
button{
    width:100%;
    border:none;
    border-radius:10px;
    padding:12px;
    margin-top:8px;
    background:#374151;
    color:white;
    font-size:14px;
}

button{
    background:#4f46e5;
    cursor:pointer;
}

button:active{
    transform:scale(.98);
}

.hidden{
    display:none !important;
}

.row{
    display:flex;
    gap:8px;
}

.row > *{
    flex:1;
}

.record{
    background:#111827;
    border-radius:10px;
    padding:10px;
    margin-top:8px;
    font-size:12px;
}

.url-item{
    background:#111827;
    padding:10px;
    border-radius:10px;
    margin-top:8px;
}

.modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.75);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:15px;
    z-index:9999;
}

.modal-box{
    width:100%;
    max-width:500px;
    background:#1f2937;
    border-radius:14px;
    padding:15px;
}

.chk-container {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    margin-top: 10px;
    color: #9ca3af;
}

.chk-container input {
    width: auto;
    margin-top: 0;
}

/* Estilos para lista de usuarios en modal masivo */
.user-list-massive {
    max-height: 250px;
    overflow-y: auto;
    background: #111827;
    margin: 10px 0;
    padding: 10px;
    border-radius: 10px;
}
.user-item-massive {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 0;
    border-bottom: 1px solid #374151;
}
.user-item-massive:last-child { border: none; }
.user-item-massive input { width: auto; margin: 0; }

</style>

</head>

<body>

<div class="box" id="loginBox">

    <h3>Panel Administrativo</h3>

    <input
        type="password"
        id="adminCode"
        placeholder="Código administrador"
    >

    <button onclick="loginAdmin()">
        Ingresar
    </button>

</div>



<div id="panel"
class="hidden">

    <div class="box">

        <h3>Crear Registro</h3>

        <input
            type="text"
            id="cuil"
            maxlength="11"
            placeholder="CUIL 11 dígitos"
        >

        <select id="url_select"></select>
        <input type="text" id="url_manual_input" class="hidden" placeholder="https://url-manual.com">

        <div class="chk-container">
            <input type="checkbox" id="chk_manual_create" onchange="toggleManual('url_select', 'url_manual_input', this)">
            <label for="chk_manual_create">Ingresar URL manualmente</label>
        </div>

        <input
            type="datetime-local"
            id="vencimiento"
        >

        <button onclick="crearRegistro()">
            Crear Registro
        </button>

    </div>

    <div class="box">
        <h3>Carga Masiva (users.json)</h3>
        <input type="file" id="massive_file_input" accept=".json">
        <button onclick="procesarArchivoMasivo()" style="background: #8b5cf6;">Cargar Archivo</button>
    </div>



    <div class="box">

        <h3>Administrar URLs</h3>

        <input
            type="text"
            id="new_url"
            placeholder="https://dominio.com"
        >

        <button onclick="agregarURL()">
            Agregar URL
        </button>

        <div id="url_list"></div>

    </div>



    <div class="box">

        <h3>Generador Code</h3>

        <select id="generator_select"></select>
        <input type="text" id="gen_manual_input" class="hidden" placeholder="https://url-manual.com">

        <div class="chk-container">
            <input type="checkbox" id="chk_manual_gen" onchange="toggleManual('generator_select', 'gen_manual_input', this)">
            <label for="chk_manual_gen">Ingresar URL manualmente</label>
        </div>

        <button onclick="generarCode()">
            Generar Code
        </button>

        <div class="row">
            <input
                type="text"
                id="generated_code"
                readonly
                placeholder="Código generado"
            >
            <button onclick="copiarAlPortapapeles()" style="width: 100px; background: #10b981;">Copiar</button>
        </div>

    </div>



    <div class="box">

        <h3>Cambio Masivo URL</h3>

        <select id="massive_url_select"></select>

        <button onclick="cambioMasivo()">
            Aplicar Cambio
        </button>

    </div>



    <div class="box">

        <h3>Registros</h3>

        <div id="records"></div>

    </div>

</div>



<script>

let urls = [];
let tempMassiveData = null;



function loginAdmin(){

    const code =
        document.getElementById('adminCode')
        .value
        .trim();

    if(code !== '<?= $adminCode ?>'){

        alert('Código inválido');

        return;
    }

    document
        .getElementById('loginBox')
        .classList
        .add('hidden');

    document
        .getElementById('panel')
        .classList
        .remove('hidden');

    cargarTodo();
}



async function cargarTodo(){

    const response =
        await fetch('?action=list');

    const data =
        await response.json();

    urls = data.urls || [];

    renderURLs();

    renderURLList();

    renderRecords(data.records || []);
}



function renderURLs(){

    const selects = [

        document.getElementById('url_select'),

        document.getElementById('generator_select'),

        document.getElementById('massive_url_select')
    ];

    selects.forEach(select => {

        select.innerHTML = '';

        urls.forEach(url => {

            const option =
                document.createElement('option');

            option.value = url;

            option.innerText = url;

            select.appendChild(option);
        });
    });
}



function renderURLList(){

    const container =
        document.getElementById('url_list');

    container.innerHTML = '';

    urls.forEach(url => {

        const div =
            document.createElement('div');

        div.className = 'url-item';

        div.innerHTML = `

            <div>${url}</div>

            <div class="row">

                <button
                onclick="editarURL('${url}')"
                >
                    Editar
                </button>

                <button
                onclick="eliminarURL('${url}')"
                >
                    Eliminar
                </button>

            </div>
        `;

        container.appendChild(div);
    });
}



async function agregarURL(){

    const url =
        document.getElementById('new_url')
        .value
        .trim();

    if(!url){

        alert('Ingresar URL');

        return;
    }

    const response =
        await fetch('?action=add_url', {

            method:'POST',

            headers:{
                'Content-Type':'application/json'
            },

            body: JSON.stringify({
                url:url
            })
        });

    const data =
        await response.json();

    if(data.status === 'success'){

        document.getElementById('new_url').value='';

        cargarTodo();
    }
}



async function editarURL(oldUrl){

    const newUrl =
        prompt(
            'Modificar URL',
            oldUrl
        );

    if(!newUrl){
        return;
    }

    const response =
        await fetch('?action=update_url', {

            method:'POST',

            headers:{
                'Content-Type':'application/json'
            },

            body: JSON.stringify({

                old:oldUrl,

                new:newUrl
            })
        });

    const data =
        await response.json();

    if(data.status === 'success'){

        cargarTodo();
    }
}



async function eliminarURL(url){

    if(!confirm('Eliminar URL?')){
        return;
    }

    const response =
        await fetch('?action=delete_url', {

            method:'POST',

            headers:{
                'Content-Type':'application/json'
            },

            body: JSON.stringify({
                url:url
            })
        });

    const data =
        await response.json();

    if(data.status === 'success'){

        cargarTodo();
    }
}


function toggleManual(selectId, inputId, chk) {
    const selectEl = document.getElementById(selectId);
    const inputEl = document.getElementById(inputId);
    if(chk.checked) {
        selectEl.classList.add('hidden');
        inputEl.classList.remove('hidden');
    } else {
        selectEl.classList.remove('hidden');
        inputEl.classList.add('hidden');
    }
}


function generarCode(){
    let url = '';
    const isManual = document.getElementById('chk_manual_gen').checked;

    if(isManual) {
        url = document.getElementById('gen_manual_input').value.trim();
    } else {
        url = document.getElementById('generator_select').value;
    }

    if(!url) {
        alert('Debe seleccionar o ingresar una URL');
        return;
    }

    document.getElementById('generated_code').value = btoa(url);
}

async function copiarAlPortapapeles() {
    const input = document.getElementById('generated_code');
    if(!input.value) return;

    try {
        await navigator.clipboard.writeText(input.value);
        alert('Código copiado al portapapeles');
    } catch (err) {
        // Fallback para dispositivos viejos
        input.select();
        document.execCommand('copy');
        alert('Código copiado al portapapeles');
    }
}



async function cambioMasivo(){

    const url =
        document.getElementById('massive_url_select')
        .value;

    if(!confirm('Aplicar cambio masivo?')){
        return;
    }

    const response =
        await fetch('?action=massive_url_change', {

            method:'POST',

            headers:{
                'Content-Type':'application/json'
            },

            body: JSON.stringify({
                url:url
            })
        });

    const data =
        await response.json();

    if(data.status === 'success'){

        cargarTodo();
    }
}



function renderRecords(records){

    const container =
        document.getElementById('records');

    container.innerHTML = '';

    records.forEach(record => {

        const div =
            document.createElement('div');

        div.className = 'record';

        div.innerHTML = `

            <div><b>CUIL:</b> ${record.cuil}</div>

            <div><b>TOKEN:</b> ${record.token}</div>

            <div><b>URL:</b> ${record.url_sistema}</div>

            <div><b>CREADO:</b> ${record.fecha_creacion}</div>

            <div><b>VENCE:</b> ${record.vencimiento_token}</div>

            <div>
                <b>ESTADO:</b>
                ${record.suspender_token ? 'SUSPENDIDO' : 'ACTIVO'}
            </div>

            <div class="row" style="margin-top:10px;">

                <button
                    onclick='editarRegistro(${JSON.stringify(record)})'
                >
                    Modificar
                </button>

                <button
                    onclick='eliminarRegistro("${record.id}")'
                    style="background:#dc2626;"
                >
                    Borrar
                </button>

            </div>
        `;

        container.appendChild(div);
    });
}



function editarRegistro(record){

    const modal = document.createElement('div');

    modal.className = 'modal';

    modal.innerHTML = `

        <div class="modal-box">

            <h3>Modificar Registro</h3>

            <div style="margin-top:10px;">
                <b>CUIL:</b> ${record.cuil}
            </div>

            <div style="margin-top:10px;">
                <b>TOKEN:</b> ${record.token}
            </div>

            <input
                type="text"
                id="edit_url"
                value="${record.url_sistema}"
                placeholder="URL sistema"
            >

            <input
                type="text"
                id="edit_vencimiento"
                value="${record.vencimiento_token}"
                placeholder="YYYY-MM-DD HH:MM:SS"
            >

            <select id="edit_estado">

                <option value="false"
                    ${record.suspender_token ? '' : 'selected'}>
                    ACTIVO
                </option>

                <option value="true"
                    ${record.suspender_token ? 'selected' : ''}>
                    SUSPENDIDO
                </option>

            </select>

            <div class="row" style="margin-top:12px;">

                <button onclick="guardarEdicionRegistro('${record.id}')">
                    Guardar
                </button>

                <button
                    style="background:#dc2626;"
                    onclick="cerrarModal()"
                >
                    Cancelar
                </button>

            </div>

        </div>
    `;

    document.body.appendChild(modal);
}



function cerrarModal(){

    const modal =
        document.querySelector('.modal');

    if(modal){

        modal.remove();
    }
}



async function guardarEdicionRegistro(id){

    const nuevaURL =
        document.getElementById('edit_url')
        .value
        .trim();

    const nuevoVencimiento =
        document.getElementById('edit_vencimiento')
        .value
        .trim();

    const nuevoEstado =
        document.getElementById('edit_estado')
        .value === 'true';

    if(!nuevaURL){

        alert('Ingresar URL');

        return;
    }

    if(!nuevoVencimiento){

        alert('Ingresar vencimiento');

        return;
    }

    const response =
        await fetch('?action=update_record', {

            method:'POST',

            headers:{
                'Content-Type':'application/json'
            },

            body: JSON.stringify({

                id:id,

                url_sistema:nuevaURL,

                vencimiento_token:nuevoVencimiento,

                suspender_token:nuevoEstado
            })
        });

    const data =
        await response.json();

    if(data.status === 'success'){

        cerrarModal();

        alert('Registro actualizado');

        cargarTodo();

    }else{

        alert(data.message || 'Error');
    }
}



async function eliminarRegistro(id){

    if(!confirm('¿Eliminar registro?')){
        return;
    }

    const response =
        await fetch('?action=delete_record', {

            method:'POST',

            headers:{
                'Content-Type':'application/json'
            },

            body: JSON.stringify({
                id:id
            })
        });

    const data =
        await response.json();

    if(data.status === 'success'){

        alert('Registro eliminado');

        cargarTodo();

    }else{

        alert(data.message || 'Error');
    }
}



async function crearRegistro(){

    const cuil =
        document.getElementById('cuil')
        .value
        .trim();

    let url = '';
    const isManual = document.getElementById('chk_manual_create').checked;

    if(isManual) {
        url = document.getElementById('url_manual_input').value.trim();
    } else {
        url = document.getElementById('url_select').value;
    }

    const venc =
        document.getElementById('vencimiento')
        .value
        .replace('T',' ');

    if(!cuil) { alert('Ingresar CUIL'); return; }
    if(!url) { alert('Ingresar URL'); return; }

    const response =
        await fetch('?action=create_record', {

            method:'POST',

            headers:{
                'Content-Type':'application/json'
            },

            body: JSON.stringify({

                cuil:cuil,

                url_sistema:url,

                vencimiento_token:venc
            })
        });

    const data =
        await response.json();

    if(data.status === 'success'){

        alert('Registro creado');

        cargarTodo();

    }else{

        alert(data.message || 'Error');
    }
}

// ======================================================
// FUNCIONES MASIVAS (INTEGRACIÓN USERS.JSON)
// ======================================================
function procesarArchivoMasivo() {
    const fileInput = document.getElementById('massive_file_input');
    if (!fileInput.files.length) return alert("Selecciona un archivo .json");

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            tempMassiveData = JSON.parse(e.target.result);
            abrirModalMasivo();
        } catch (err) { alert("Error al leer el archivo JSON."); }
    };
    reader.readAsText(fileInput.files[0]);
}

function abrirModalMasivo() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    
    const cuils = Object.keys(tempMassiveData);
    
    let itemsHTML = cuils.map(c => {
        const u = tempMassiveData[c];
        return `
            <div class="user-item-massive">
                <input type="checkbox" class="massive-chk" value="${c}" id="chk_${c}" checked>
                <label for="chk_${c}">
                    <b>${c}</b> - <small>${u.email || ''}</small> 
                    <br><span style="font-size:10px; color:#9ca3af;">Vence: ${u.vencimiento} | ${u.suspendido ? 'SUSPENDIDO' : 'ACTIVO'}</span>
                </label>
            </div>
        `;
    }).join('');

    modal.innerHTML = `
        <div class="modal-box">
            <h3>Seleccionar Usuarios</h3>
            <div class="user-list-massive">${itemsHTML}</div>
            
            <div style="background:#111827; padding:10px; border-radius:10px; margin-top:10px;">
                <label style="font-size:12px; color:#9ca3af;">Asignar URL destino:</label>
                <select id="massive_modal_url">
                    ${urls.map(u => `<option value="${u}">${u}</option>`).join('')}
                </select>
            </div>
            
            <div class="row" style="margin-top:15px;">
                <button onclick="enviarCargaMasiva()">Registrar Seleccionados</button>
                <button onclick="cerrarModal()" style="background:#dc2626;">Cancelar</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

async function enviarCargaMasiva() {
    const checks = document.querySelectorAll('.massive-chk:checked');
    const url = document.getElementById('massive_modal_url').value;

    if (!checks.length) return alert("Selecciona al menos un usuario.");
    if (!url) return alert("Selecciona una URL destino.");

    const batch = Array.from(checks).map(chk => {
        const cuil = chk.value;
        const u = tempMassiveData[cuil];
        return {
            cuil: cuil,
            url_sistema: url,
            vencimiento_token: u.vencimiento,
            suspender_token: u.suspendido,
            fecha_creacion: u.registerdatatime
        };
    });

    const response = await fetch('?action=create_massive_records', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ records: batch })
    });

    const data = await response.json();
    if (data.status === 'success') {
        cerrarModal();
        alert(`Éxito: ${data.added} registros creados.`);
        cargarTodo();
    } else {
        alert("Error al procesar la carga.");
    }
}

</script>

</body>
</html>