// Frontend-only app logic for the main product page.

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
    // Backfill resolvedAt for already-resolved items
    state.problems.forEach(p => { if(p.status === 'resolved' && !p.resolvedAt) p.resolvedAt = new Date().toISOString(); });
    state.tasks.forEach(t => { if(t.status === 'resolved' && !t.resolvedAt) t.resolvedAt = new Date().toISOString(); });
    cleanupResolved();
}

function cleanupResolved(){
    const TWO_DAYS = 2 * 24 * 60 * 60 * 1000;
    const now = Date.now();
    let changed = false;
    const pLen = state.problems.length;
    state.problems = state.problems.filter(p => {
        if(p.status === 'resolved' && p.resolvedAt) return (now - new Date(p.resolvedAt).getTime()) < TWO_DAYS;
        return true;
    });
    const tLen = state.tasks.length;
    state.tasks = state.tasks.filter(t => {
        if(t.status === 'resolved' && t.resolvedAt) return (now - new Date(t.resolvedAt).getTime()) < TWO_DAYS;
        return true;
    });
    if(state.problems.length !== pLen || state.tasks.length !== tLen) saveState();
}

function saveState(){ localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); }

// Render products grid
function renderProducts(){
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = '';
    PRODUCTS.forEach(p => {
        const card = document.createElement('article');
        card.className = 'instruction-card';
        card.setAttribute('data-id', p.id);

        card.innerHTML = `
            <div class="instruction-content">
                <div class="instruction-text">
                    <h3>${p.name}</h3>
                    <p>Klikk for bruksanvisning</p>
                </div>
                <div class="instruction-image"><img src="${p.img}" alt="${p.name}"></div>
            </div>
            <button class="info-icon-btn" data-id="${p.id}" title="Info">i</button>
        `;

        grid.appendChild(card);
    });

    // Whole card -> open manual
    document.querySelectorAll('.instruction-card').forEach(card => {
        card.addEventListener('click', () => {
            const productId = Number(card.getAttribute('data-id'));
            openManualPreview(productId);
        });
    });

    // Info icon -> show inventory info
    document.querySelectorAll('.info-icon-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const id = Number(btn.getAttribute('data-id'));
            const product = PRODUCTS.find(p => p.id === id);
            alert(`${product.name}\nLager: ${state.inventory[id] ?? 0}`);
        });
    });
}

// Navigate to manual page
function openManualPreview(productId){
    const product = PRODUCTS.find(p => p.id === productId);
    const content = state.manuals[productId];

    if(!content){
        alert('Ingen manual er opprettet for ' + product.name + ' enda.');
        return;
    }

    window.location.href = 'manual.html?id=' + productId;
}

// Report modal
function openReport(){ document.getElementById('reportModal').style.display = 'flex'; }
function closeReport(){ document.getElementById('reportModal').style.display = 'none'; }

window.addEventListener('click', function(e){
    if(e.target.classList.contains('modal')) e.target.style.display = 'none';
});

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

// Initialize
loadState();
renderProducts();
wireReportForm();

// Expose some helpers globally so markup buttons still work
window.openManualPreview = openManualPreview;
window.openReport = openReport;
window.closeReport = closeReport;
