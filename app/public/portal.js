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

const tripVehicle=document.querySelector('#vehicle_id');
const openingMileage=document.querySelector('#opening');
if(tripVehicle&&openingMileage){
 const expected=document.querySelector('#expected_mileage');
 const verified=document.querySelector('#mileage_verified');
 const note=document.querySelector('#mileage_note');
 const message=document.querySelector('#mileage-message');
 const updateMessage=()=>{
  const mileage=Number(expected.value);
  const gap=Math.round((Number(openingMileage.value)-mileage)*10)/10;
  note.required=gap!==0;
  message.textContent=`Current vehicle mileage: ${mileage.toLocaleString()} km. Verify this against the odometer.`+(gap!==0?` Difference: ${gap>0?'+':''}${gap} km. Enter a reason; this gap will be logged.`:'');
 };
 const selectMileage=(initial=false)=>{
  const option=tripVehicle.selectedOptions[0];
  if(!option?.dataset.mileage){return}
  if(!initial||expected.value===''){
   expected.value=option.dataset.mileage;openingMileage.value=option.dataset.mileage;verified.checked=false;
  }
  updateMessage();
 };
 tripVehicle.addEventListener('change',()=>selectMileage());
 openingMileage.addEventListener('input',()=>{verified.checked=false;updateMessage()});
 selectMileage(true);
}

const operationVehicle=document.querySelector('#operation-vehicle');
operationVehicle?.addEventListener('change',()=>{document.querySelector('#operation-version').value=operationVehicle.selectedOptions[0]?.dataset.version??''});
