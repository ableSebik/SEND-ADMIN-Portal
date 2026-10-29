const toggle=document.querySelector('.menu-toggle');
const sidebar=document.querySelector('.sidebar');
const mobile=window.matchMedia('(max-width:760px)');
let collapsed=false;
try{collapsed=localStorage.getItem('sidebar-collapsed')==='true'}catch{}
function updateSidebar(){
 document.body.classList.toggle('sidebar-collapsed',!mobile.matches&&collapsed);
 toggle?.setAttribute('aria-expanded',String(mobile.matches?sidebar?.classList.contains('open'):!collapsed));
}
updateSidebar();
document.querySelector('.sidebar-close')?.addEventListener('click',()=>{sidebar.classList.remove('open');updateSidebar();toggle?.focus()});
toggle?.addEventListener('click',()=>{
 if(mobile.matches)sidebar.classList.toggle('open');
 else{collapsed=!collapsed;try{localStorage.setItem('sidebar-collapsed',String(collapsed))}catch{}}
 updateSidebar();
});
mobile.addEventListener('change',()=>{sidebar?.classList.remove('open');updateSidebar()});
document.addEventListener('keydown',event=>{if(event.key==='Escape'){sidebar?.classList.remove('open');document.querySelector('.account-menu')?.removeAttribute('open');updateSidebar()}});
document.addEventListener('click',event=>{
 const menu=document.querySelector('.account-menu');if(menu&&!menu.contains(event.target))menu.removeAttribute('open');
 if(mobile.matches&&sidebar&&!sidebar.contains(event.target)&&!toggle?.contains(event.target)){sidebar.classList.remove('open');updateSidebar()}
});
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirm))event.preventDefault()}));
