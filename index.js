// Frontend-only app logic for products, inventory, and admin panels.

// Sample product data (frontend). When connecting PHP/SQL later, replace fetches.
const PRODUCTS = [
    { id: 1, name: '3D-printer', img: 'https://via.placeholder.com/320x220?text=3D', manual: '3d-printer.html' },
    { id: 2, name: 'Laser Cutter', img: 'https://via.placeholder.com/320x220?text=Laser', manual: 'laser.html' },
    { id: 3, name: 'CNC Mill', img: 'https://via.placeholder.com/320x220?text=CNC', manual: 'cnc.html' },
    { id: 4, name: 'Soldering Station', img: 'https://via.placeholder.com/320x220?text=Soldering', manual: 'solder.html' },
    { id: 5, name: 'Vinyl Cutter', img: 'https://via.placeholder.com/320x220?text=Vinyl', manual: 'vinyl.html' },
    { id: 6, name: 'Electronics Bench', img: 'https://via.placeholder.com/320x220?text=Electronics', manual: 'electronics.html' }
];

// inventory and problems are stored in localStorage for the frontend demo
const STORAGE_KEY = 'ms_frontend_state_v1';
let state = {
    inventory: {},       // productId -> quantity
    problems: [],        // {id, title, desc, status, from}
    tasks: [],           // tasks assigned by secondary admin to super admin
    manuals: {},         // productId -> HTML string (manual content)
    imageFolders: [],    // [{id, name, parentId}] parentId null = root
    images: []           // [{id, name, folderId, dataUrl, createdAt}]
};

function loadState(){
    const raw = localStorage.getItem(STORAGE_KEY);
    if(raw){
        try{ state = Object.assign(state, JSON.parse(raw)); } catch(e){ console.warn('Could not parse state', e); }
        // Migrate: move secondary-admin tasks from problems into tasks array
        const secTasks = state.problems.filter(p => p.from === 'secondary');
        if(secTasks.length > 0){
            secTasks.forEach(t => {
                if(!state.tasks.find(x => x.id === t.id)){
                    state.tasks.push(t);
                }
            });
            state.problems = state.problems.filter(p => p.from !== 'secondary');
            saveState();
        }
    } else {
        // seed inventory
        PRODUCTS.forEach(p => state.inventory[p.id] = 1);
        state.problems.push({id:1, title:'Bed heater error (3D)', desc:'Varmer slår seg av etter 2 min', status:'open', from:'user'});
    }
}

function saveState(){ localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); }

// Render products grid
function renderProducts(){
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = '';
    PRODUCTS.forEach(p => {
        const card = document.createElement('article');
        card.className = 'instruction-card';

        card.innerHTML = `
            <div class="instruction-content">
                <div class="instruction-text"><h3>${p.name}</h3><p>Les bruksanvisning og sikkerhet</p></div>
                <div class="instruction-image"><img src="${p.img}" alt="${p.name}"></div>
            </div>
            <div class="instruction-footer">
                <button class="manual-btn" data-id="${p.id}">Brukermanual${state.manuals[p.id] ? ' &#10003;' : ''}</button>
                <button class="info-btn" data-id="${p.id}">Info</button>
            </div>
        `;

        grid.appendChild(card);
    });

    // attach handlers
    document.querySelectorAll('.manual-btn').forEach(b => b.addEventListener('click', (e)=>{
        e.stopPropagation();
        const productId = Number(b.getAttribute('data-id'));
        openManualPreview(productId);
    }));

    document.querySelectorAll('.info-btn').forEach(b => b.addEventListener('click', (e)=>{
        e.stopPropagation();
        const id = Number(b.getAttribute('data-id'));
        alert(`${PRODUCTS.find(p=>p.id===id).name}\nLager: ${state.inventory[id] ?? 0}`);
    }));
}

// Guide modal functions
function openManualPreview(productId){
    const product = PRODUCTS.find(p => p.id === productId);
    const content = state.manuals[productId];

    if(!content){
        alert('Ingen manual er opprettet for ' + product.name + ' enda.');
        return;
    }

    const modal = document.getElementById('guideModal');
    const iframe = document.getElementById('guideFrame');
    iframe.style.display = 'none';

    let contentDiv = document.getElementById('manualContentDisplay');
    if(!contentDiv){
        contentDiv = document.createElement('div');
        contentDiv.id = 'manualContentDisplay';
        contentDiv.className = 'manual-content-display';
        modal.querySelector('.modal-box').appendChild(contentDiv);
    }
    contentDiv.style.display = 'block';
    contentDiv.innerHTML = '<h2>' + product.name + ' - Brukermanual</h2>' + content;
    modal.style.display = 'flex';
}

function closeGuide(){
    document.getElementById('guideModal').style.display = 'none';
    document.getElementById('guideFrame').src = '';
    document.getElementById('guideFrame').style.display = '';
    const contentDiv = document.getElementById('manualContentDisplay');
    if(contentDiv) contentDiv.style.display = 'none';
}

// Report modal
function openReport(){ document.getElementById('reportModal').style.display = 'flex'; }
function closeReport(){ document.getElementById('reportModal').style.display = 'none'; }

window.addEventListener('click', function(e){
    if(e.target.classList.contains('modal')) e.target.style.display = 'none';
});

// ===== ADMIN UI LOGIC =====
let currentRole = 'guest';
let currentImageFolder = null; // transient UI state for image browser

function setRole(role){
    currentRole = role;
    const secPanel = document.getElementById('secondaryAdminPanel');
    const headPanel = document.getElementById('headAdminPanel');

    secPanel.classList.add('hidden');
    headPanel.classList.add('hidden');

    if(role === 'secondary'){
        secPanel.classList.remove('hidden');
        renderSecondaryAdmin();
    } else if(role === 'super'){
        headPanel.classList.remove('hidden');
        showHeadTab('hInventory');
    }
}

// Head admin tab switching
function showHeadTab(tabName){
    document.querySelectorAll('.htab-btn').forEach(b =>
        b.classList.toggle('active', b.getAttribute('data-tab') === tabName)
    );
    ['hInventoryTab','hProblemsTab','hTasksTab','hManualsTab','hImagesTab'].forEach(id => {
        document.getElementById(id).classList.toggle('hidden', id !== tabName + 'Tab');
    });
    renderHeadAdmin(tabName);
}

// ===== SECONDARY ADMIN PANEL =====
function renderSecondaryAdmin(){
    const inv = document.getElementById('secInventoryList');
    inv.innerHTML = '';
    PRODUCTS.forEach(p => {
        const row = document.createElement('div');
        row.className = 'inventory-row';
        row.innerHTML = `
            <img src="${p.img}" alt="${p.name}">
            <div style="flex:1">
                <div><strong>${p.name}</strong></div>
                <div><input type="number" min="0" data-id="${p.id}" value="${state.inventory[p.id] ?? 0}"></div>
            </div>
        `;
        inv.appendChild(row);
    });

    // Inventory change handlers
    inv.querySelectorAll('input[type=number]').forEach(inp => inp.addEventListener('change', ()=>{
        const id = Number(inp.getAttribute('data-id'));
        state.inventory[id] = Number(inp.value);
        saveState();
    }));

    // Populate product dropdown for task form
    const select = document.getElementById('secTaskProduct');
    select.innerHTML = PRODUCTS.map(p => '<option value="' + p.id + '">' + p.name + '</option>').join('');

    // Task submit handler (re-attach each render)
    const submitBtn = document.getElementById('secTaskSubmit');
    const newBtn = submitBtn.cloneNode(true);
    submitBtn.parentNode.replaceChild(newBtn, submitBtn);
    newBtn.addEventListener('click', ()=>{
        const productId = Number(document.getElementById('secTaskProduct').value);
        const title = document.getElementById('secTaskTitle').value.trim();
        const desc = document.getElementById('secTaskDesc').value.trim();
        if(!title){ alert('Skriv inn en tittel'); return; }

        state.tasks.push({
            id: Date.now(),
            title: title,
            desc: desc || 'Opprettet fra sekundaeradmin',
            status: 'open',
            from: 'secondary',
            productId: productId,
            createdAt: new Date().toISOString()
        });
        saveState();
        document.getElementById('secTaskTitle').value = '';
        document.getElementById('secTaskDesc').value = '';
        alert('Oppgave sendt til hovedadmin!');
    });
}

// ===== HEAD ADMIN PANEL =====
function renderHeadAdmin(tabName){
    if(tabName === 'hInventory') renderHeadInventory();
    else if(tabName === 'hProblems') renderHeadProblems();
    else if(tabName === 'hTasks') renderHeadTasks();
    else if(tabName === 'hManuals') renderHeadManuals();
    else if(tabName === 'hImages') renderHeadImages();
}

function renderHeadInventory(){
    const container = document.getElementById('hInventoryTab');
    container.innerHTML = '<h3>Inventar</h3>';
    PRODUCTS.forEach(p => {
        const row = document.createElement('div');
        row.className = 'inventory-row';
        row.innerHTML = `
            <img src="${p.img}" alt="${p.name}">
            <div style="flex:1">
                <div><strong>${p.name}</strong></div>
                <div><input type="number" min="0" data-id="${p.id}" value="${state.inventory[p.id] ?? 0}"></div>
            </div>
        `;
        container.appendChild(row);
    });
    container.querySelectorAll('input[type=number]').forEach(inp => inp.addEventListener('change', ()=>{
        const id = Number(inp.getAttribute('data-id'));
        state.inventory[id] = Number(inp.value);
        saveState();
    }));
}

function renderHeadProblems(){
    const container = document.getElementById('hProblemsTab');
    container.innerHTML = '<h3>Problemer fra brukere</h3>';

    if(state.problems.length === 0){
        container.innerHTML += '<p style="color:#999">Ingen problemer rapportert.</p>';
        return;
    }

    state.problems.slice().reverse().forEach(p => {
        const item = document.createElement('div');
        item.className = 'problem-item' + (p.status === 'resolved' ? ' resolved' : '');
        item.innerHTML = `
            <div><strong>${p.title}</strong> <small>(${p.from})</small></div>
            <div style="margin-top:6px">${p.desc || ''}</div>
            <div style="margin-top:8px">
                <button class="resolve-btn" data-id="${p.id}">${p.status === 'resolved' ? 'Gjenåpne' : 'Marker som løst'}</button>
            </div>
        `;
        container.appendChild(item);
    });

    container.querySelectorAll('.resolve-btn').forEach(b => b.addEventListener('click', ()=>{
        const id = Number(b.getAttribute('data-id'));
        const p = state.problems.find(x => x.id === id);
        if(p){ p.status = p.status === 'resolved' ? 'open' : 'resolved'; saveState(); renderHeadProblems(); }
    }));
}

function renderHeadTasks(){
    const container = document.getElementById('hTasksTab');
    container.innerHTML = '<h3>Oppgaver fra sekundaeradmin</h3>';

    if(state.tasks.length === 0){
        container.innerHTML += '<p style="color:#999">Ingen oppgaver.</p>';
        return;
    }

    state.tasks.slice().reverse().forEach(t => {
        const product = PRODUCTS.find(p => p.id === t.productId);
        const item = document.createElement('div');
        item.className = 'task-item' + (t.status === 'resolved' ? ' resolved' : '');
        item.innerHTML = `
            <div><strong>${t.title}</strong></div>
            <div><small>Utstyr: ${product ? product.name : 'Ukjent'}</small></div>
            <div style="margin-top:4px">${t.desc || ''}</div>
            <div style="margin-top:8px">
                <button class="resolve-task-btn" data-id="${t.id}">
                    ${t.status === 'resolved' ? 'Gjenåpne' : 'Marker som utført'}
                </button>
                ${t.status === 'resolved' ? '<button class="delete-task-btn" data-id="' + t.id + '">Slett</button>' : ''}
            </div>
        `;
        container.appendChild(item);
    });

    container.querySelectorAll('.resolve-task-btn').forEach(b => b.addEventListener('click', ()=>{
        const id = Number(b.getAttribute('data-id'));
        const t = state.tasks.find(x => x.id === id);
        if(t){ t.status = t.status === 'resolved' ? 'open' : 'resolved'; saveState(); renderHeadTasks(); }
    }));

    container.querySelectorAll('.delete-task-btn').forEach(b => b.addEventListener('click', ()=>{
        const id = Number(b.getAttribute('data-id'));
        if(confirm('Slett denne oppgaven?')){
            state.tasks = state.tasks.filter(x => x.id !== id);
            saveState();
            renderHeadTasks();
        }
    }));
}

// ===== MANUAL EDITOR =====
function renderHeadManuals(){
    const container = document.getElementById('hManualsTab');
    container.innerHTML = `
        <h3>Rediger brukermanualer</h3>
        <label>Velg utstyr:</label>
        <select id="manualProductSelect">
            ${PRODUCTS.map(p => '<option value="' + p.id + '">' + p.name + '</option>').join('')}
        </select>
        <div class="manual-toolbar">
            <button data-cmd="bold" title="Fet"><b>B</b></button>
            <button data-cmd="italic" title="Kursiv"><i>I</i></button>
            <button data-cmd="underline" title="Understrek"><u>U</u></button>
            <button data-cmd="insertUnorderedList" title="Punktliste">• Liste</button>
            <button data-cmd="insertOrderedList" title="Nummerert liste">1. Liste</button>
            <button data-cmd="formatBlock" data-val="h2" title="Overskrift">H2</button>
            <button data-cmd="formatBlock" data-val="h3" title="Underoverskrift">H3</button>
            <button data-cmd="formatBlock" data-val="p" title="Avsnitt">P</button>
        </div>
        <div id="manualEditor" contenteditable="true" class="manual-editor"></div>
        <div class="manual-actions">
            <button id="manualSaveBtn">Lagre manual</button>
            <button id="manualPreviewBtn">Forhåndsvis</button>
            <span id="manualSaveStatus"></span>
        </div>
    `;

    // Load content for first product
    loadManualContent(PRODUCTS[0].id);

    // Product selector
    document.getElementById('manualProductSelect').addEventListener('change', e => {
        loadManualContent(Number(e.target.value));
    });

    // Toolbar formatting buttons
    container.querySelectorAll('.manual-toolbar button').forEach(btn => {
        btn.addEventListener('click', ()=>{
            const cmd = btn.getAttribute('data-cmd');
            const val = btn.getAttribute('data-val') || null;
            document.execCommand(cmd, false, val);
            document.getElementById('manualEditor').focus();
        });
    });

    // Save
    document.getElementById('manualSaveBtn').addEventListener('click', ()=>{
        const productId = Number(document.getElementById('manualProductSelect').value);
        state.manuals[productId] = document.getElementById('manualEditor').innerHTML;
        saveState();
        document.getElementById('manualSaveStatus').textContent = 'Lagret!';
        setTimeout(()=> document.getElementById('manualSaveStatus').textContent = '', 2000);
        renderProducts(); // update checkmarks on cards
    });

    // Preview
    document.getElementById('manualPreviewBtn').addEventListener('click', ()=>{
        const productId = Number(document.getElementById('manualProductSelect').value);
        const content = document.getElementById('manualEditor').innerHTML;
        if(!content || content === '<br>'){ alert('Skriv noe innhold først.'); return; }
        // Temporarily save for preview
        state.manuals[productId] = content;
        openManualPreview(productId);
    });
}

function loadManualContent(productId){
    const editor = document.getElementById('manualEditor');
    const product = PRODUCTS.find(p => p.id === productId);
    editor.innerHTML = state.manuals[productId] || '<h2>' + product.name + '</h2><p>Skriv brukermanual her...</p>';
}

// ===== IMAGE / FOLDER SYSTEM =====
function renderHeadImages(){
    const container = document.getElementById('hImagesTab');
    const subfolders = state.imageFolders.filter(f => f.parentId === currentImageFolder);
    const folderImages = state.images.filter(img => img.folderId === currentImageFolder);

    container.innerHTML = `
        <h3>Bilder og mapper</h3>
        <div class="image-breadcrumbs">${buildBreadcrumbs(currentImageFolder)}</div>
        <div class="image-toolbar">
            <button id="createFolderBtn">+ Ny mappe</button>
            <button id="uploadImageBtn">+ Last opp bilde</button>
            <input type="file" id="imageFileInput" accept="image/*" multiple style="display:none">
            <span class="storage-info" id="storageInfo"></span>
        </div>
        <div class="folder-grid">
            ${subfolders.map(f => `
                <div class="folder-item" data-folder-id="${f.id}">
                    <div class="folder-icon">&#128193;</div>
                    <div class="folder-name">${f.name}</div>
                    <div class="folder-actions">
                        <button class="rename-folder-btn" data-id="${f.id}">Endre navn</button>
                        <button class="delete-folder-btn" data-id="${f.id}">Slett</button>
                    </div>
                </div>
            `).join('')}
        </div>
        ${folderImages.length === 0 && subfolders.length === 0 ? '<p style="color:#999">Ingen bilder eller mapper her.</p>' : ''}
        <div class="image-grid">
            ${folderImages.map(img => `
                <div class="image-item" data-image-id="${img.id}">
                    <img src="${img.dataUrl}" alt="${img.name}">
                    <div class="image-name">${img.name}</div>
                    <div class="image-actions">
                        <button class="move-image-btn" data-id="${img.id}">Flytt</button>
                        <button class="delete-image-btn" data-id="${img.id}">Slett</button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    updateStorageInfo();

    // Create folder
    document.getElementById('createFolderBtn').addEventListener('click', ()=>{
        const name = prompt('Mappenavn:');
        if(!name) return;
        state.imageFolders.push({ id: Date.now(), name: name, parentId: currentImageFolder });
        saveState();
        renderHeadImages();
    });

    // Upload image
    document.getElementById('uploadImageBtn').addEventListener('click', ()=>{
        document.getElementById('imageFileInput').click();
    });
    document.getElementById('imageFileInput').addEventListener('change', (e)=>{
        handleImageUpload(e.target.files);
    });

    // Navigate into folder (click on folder icon/name)
    container.querySelectorAll('.folder-item').forEach(el => {
        el.addEventListener('click', (e)=>{
            if(e.target.closest('.folder-actions')) return; // don't navigate when clicking action buttons
            const folderId = Number(el.getAttribute('data-folder-id'));
            currentImageFolder = folderId;
            renderHeadImages();
        });
    });

    // Breadcrumb navigation
    container.querySelectorAll('.breadcrumb-link').forEach(a => {
        a.addEventListener('click', (e)=>{
            e.preventDefault();
            const val = a.getAttribute('data-folder-id');
            currentImageFolder = val === 'null' ? null : Number(val);
            renderHeadImages();
        });
    });

    // Rename folder
    container.querySelectorAll('.rename-folder-btn').forEach(b => b.addEventListener('click', (e)=>{
        e.stopPropagation();
        const id = Number(b.getAttribute('data-id'));
        const folder = state.imageFolders.find(f => f.id === id);
        if(!folder) return;
        const newName = prompt('Nytt navn:', folder.name);
        if(newName && newName !== folder.name){
            folder.name = newName;
            saveState();
            renderHeadImages();
        }
    }));

    // Delete folder
    container.querySelectorAll('.delete-folder-btn').forEach(b => b.addEventListener('click', (e)=>{
        e.stopPropagation();
        const id = Number(b.getAttribute('data-id'));
        if(!confirm('Slett denne mappen og alt innholdet?')) return;
        deleteFolderRecursive(id);
        saveState();
        renderHeadImages();
    }));

    // Move image
    container.querySelectorAll('.move-image-btn').forEach(b => b.addEventListener('click', ()=>{
        const id = Number(b.getAttribute('data-id'));
        moveImage(id);
    }));

    // Delete image
    container.querySelectorAll('.delete-image-btn').forEach(b => b.addEventListener('click', ()=>{
        const id = Number(b.getAttribute('data-id'));
        if(!confirm('Slett dette bildet?')) return;
        state.images = state.images.filter(i => i.id !== id);
        saveState();
        renderHeadImages();
    }));
}

function buildBreadcrumbs(folderId){
    const parts = [{ id: null, name: 'Rot' }];
    let current = folderId;
    while(current !== null){
        const folder = state.imageFolders.find(f => f.id === current);
        if(!folder) break;
        parts.push({ id: folder.id, name: folder.name });
        current = folder.parentId;
    }
    // Reverse the path parts (excluding root which is always first)
    if(parts.length > 1){
        const root = parts[0];
        const rest = parts.slice(1).reverse();
        parts.length = 0;
        parts.push(root, ...rest);
    }
    return parts.map((p, i) => {
        if(i === parts.length - 1) return '<span>' + p.name + '</span>';
        return '<a href="#" class="breadcrumb-link" data-folder-id="' + (p.id === null ? 'null' : p.id) + '">' + p.name + '</a>';
    }).join(' / ');
}

function handleImageUpload(files){
    Array.from(files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e){
            state.images.push({
                id: Date.now() + Math.floor(Math.random() * 1000),
                name: file.name,
                folderId: currentImageFolder,
                dataUrl: e.target.result,
                createdAt: new Date().toISOString()
            });
            saveState();
            renderHeadImages();
        };
        reader.readAsDataURL(file);
    });
}

function moveImage(imageId){
    const allFolders = [{ id: null, name: 'Rot (toppnivå)' }, ...state.imageFolders];
    const folderNames = allFolders.map((f, i) => i + ': ' + f.name).join('\n');
    const choice = prompt('Velg mappe (skriv nummer):\n' + folderNames);
    if(choice === null) return;
    const idx = parseInt(choice);
    if(isNaN(idx) || idx < 0 || idx >= allFolders.length){ alert('Ugyldig valg'); return; }

    const img = state.images.find(i => i.id === imageId);
    if(img){
        img.folderId = allFolders[idx].id;
        saveState();
        renderHeadImages();
    }
}

function deleteFolderRecursive(folderId){
    // Delete images in this folder
    state.images = state.images.filter(i => i.folderId !== folderId);
    // Find child folders and delete them recursively
    const children = state.imageFolders.filter(f => f.parentId === folderId);
    children.forEach(child => deleteFolderRecursive(child.id));
    // Delete the folder itself
    state.imageFolders = state.imageFolders.filter(f => f.id !== folderId);
}

function updateStorageInfo(){
    const el = document.getElementById('storageInfo');
    if(!el) return;
    try {
        const used = new Blob([JSON.stringify(state)]).size;
        const usedKB = (used / 1024).toFixed(1);
        el.textContent = 'Lagring: ' + usedKB + ' KB brukt';
        if(used > 4 * 1024 * 1024) el.style.color = 'red';
    } catch(e){ /* ignore */ }
}

// ===== REPORT FORM =====
function wireReportForm(){
    const submitBtn = document.getElementById('reportSubmitBtn');
    if(!submitBtn) return;
    submitBtn.addEventListener('click', ()=>{
        const title = document.getElementById('reportTitle').value.trim();
        const desc = document.getElementById('reportDesc').value.trim();
        if(!title){ alert('Skriv inn en tittel'); return; }

        state.problems.push({
            id: Date.now(),
            title: title,
            desc: desc,
            status: 'open',
            from: 'user',
            createdAt: new Date().toISOString()
        });
        saveState();

        document.getElementById('reportTitle').value = '';
        document.getElementById('reportDesc').value = '';
        closeReport();
        alert('Rapport sendt! Takk for tilbakemeldingen.');
    });
}

// Scale control
document.getElementById('scaleRange').addEventListener('input', (e)=>{
    document.documentElement.style.setProperty('--card-scale', e.target.value);
});

// Role selection
document.getElementById('roleSelect').addEventListener('change', (e)=> setRole(e.target.value));

// Head admin tab buttons
document.querySelectorAll('.htab-btn').forEach(b => b.addEventListener('click', ()=> showHeadTab(b.getAttribute('data-tab'))));

// Initialize
loadState();
renderProducts();
wireReportForm();

// Expose some helpers globally so markup buttons still work
window.openManualPreview = openManualPreview;
window.closeGuide = closeGuide;
window.openReport = openReport;
window.closeReport = closeReport;
