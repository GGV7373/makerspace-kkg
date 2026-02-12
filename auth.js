// Shared authentication utility for Makerspace KKG
// Frontend-only demo - passwords stored in plain text in localStorage.
// Replace with backend API calls when database is connected.

const AUTH_STORAGE_KEY = 'ms_auth_accounts_v1';
const SESSION_STORAGE_KEY = 'ms_auth_session_v1';

function getAccounts(){
    const raw = localStorage.getItem(AUTH_STORAGE_KEY);
    if(!raw) return [];
    try { return JSON.parse(raw); } catch(e){ return []; }
}

function saveAccounts(accounts){
    localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(accounts));
}

function seedDefaultAdmin(){
    const accounts = getAccounts();
    if(accounts.length === 0){
        accounts.push({
            id: 1,
            username: 'KKGIT',
            password: 'Adminpwd123',
            fullName: 'Superadministrator',
            role: 'HEAD_ADMIN',
            isActive: true,
            createdAt: new Date().toISOString()
        });
        saveAccounts(accounts);
    }
}

function authenticate(username, password){
    const accounts = getAccounts();
    return accounts.find(a => a.username === username && a.password === password && a.isActive) || null;
}

function getSession(){
    const raw = localStorage.getItem(SESSION_STORAGE_KEY);
    if(!raw) return null;
    try { return JSON.parse(raw); } catch(e){ return null; }
}

function setSession(account){
    localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify({
        adminId: account.id,
        username: account.username,
        fullName: account.fullName,
        role: account.role,
        loginAt: new Date().toISOString()
    }));
}

function clearSession(){
    localStorage.removeItem(SESSION_STORAGE_KEY);
}

function requireAuth(allowedRoles){
    seedDefaultAdmin();
    const session = getSession();
    if(!session || !allowedRoles.includes(session.role)){
        window.location.href = 'login.html';
        return false;
    }
    return true;
}
