// Frontend-only app logic for the main product page.

// Product data - fetched from backend API
let PRODUCTS = [];

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

// Fetch products from backend API
async function loadProducts(){
    try {
        const response = await fetch('api/products.php');
        if (!response.ok) {
            throw new Error('Failed to fetch products');
        }
        PRODUCTS = await response.json();
        console.log('Products loaded from API:', PRODUCTS);
    } catch (error) {
        console.error('Error loading products:', error);
        // Fallback to empty array - products won't display without API
        PRODUCTS = [];
    }
}

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

// Open manual in modal from database
async function openManualPreview(productId){
    const product = PRODUCTS.find(p => p.id === productId);
    
    if(!product){
        alert('Produkt ikke funnet');
        return;
    }

    try {
        const response = await fetch(`api/manual.php?id=${productId}`);
        if(!response.ok) {
            throw new Error('Failed to fetch manual');
        }
        
        const data = await response.json();
        const manualContent = document.getElementById('manualContent');
        
        manualContent.innerHTML = `
            <div class="manual-header">
                <h2>${data.name} – Bruksanvisning</h2>
            </div>
            <div class="manual-body">
                ${data.content}
            </div>
        `;
        
        document.getElementById('manualModal').style.display = 'flex';
    } catch (error) {
        console.error('Error loading manual:', error);
        alert('Kunne ikke laste bruksanvisning for ' + product.name);
    }
}

function closeManualModal(){
    document.getElementById('manualModal').style.display = 'none';
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
    submitBtn.addEventListener('click', submitReport);
}

async function submitReport(){
    const title = document.getElementById('reportTitle').value.trim();
    const desc = document.getElementById('reportDesc').value.trim();
    
    if(!title){ 
        alert('Skriv inn en tittel'); 
        return; 
    }

    try {
        const response = await fetch('api/reports.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                reporter_name: 'Anonym',
                about_text: title + ' - ' + desc,
                status: 'NEW'
            })
        });
        
        if(!response.ok) throw new Error('Failed to submit report');
        
        document.getElementById('reportTitle').value = '';
        document.getElementById('reportDesc').value = '';
        closeReport();
        alert('Rapport sendt! Takk for tilbakemeldingen.');
    } catch (error) {
        console.error(error);
        alert('Kunne ikke sende rapport');
    }
}

// Scale control
document.getElementById('scaleRange').addEventListener('input', (e)=>{
    document.documentElement.style.setProperty('--card-scale', e.target.value);
});

// Initialize
(async () => {
    await loadProducts();
    loadState();
    renderProducts();
    wireReportForm();
})();

// Expose some helpers globally so markup buttons still work
window.openManualPreview = openManualPreview;
window.openReport = openReport;
window.closeReport = closeReport;
