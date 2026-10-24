<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLocalStorage, useMediaQuery } from '@vueuse/core'
import { session, logout } from './api'
import Icon from './components/Icon.vue'
import Brand from './components/Brand.vue'
import PasswordDialog from './components/PasswordDialog.vue'
import WelcomeCelebration from './components/WelcomeCelebration.vue'

const route=useRoute(),router=useRouter(),mobile=ref(false),exitError=ref(''),leaving=ref(false),welcomeOpen=ref(false)
const collapsed=useLocalStorage('uls-menu-collapsed',false)
const smallScreen=useMediaQuery('(max-width: 760px)')
const auth=computed(()=>route.meta.public)
const requiredPassword=computed(()=>!!session.user?.must_change_password)
const links=computed(()=>[
 {to:'/',label:'Resumen general',icon:'LayoutDashboard'},
 {to:'/notas',label:session.user?.role==='student'?'Mis notas':'Gestión de notas',icon:'BookOpen'},
 ...(session.user?.role==='admin'?[{to:'/docentes',label:'Docentes',icon:'UserRound'},{to:'/estudiantes',label:'Estudiantes',icon:'Users'}]:[]),
 {to:'/grupos',label:'Grupos académicos',icon:'Layers'},
 ...(session.user?.role==='student'?[{to:'/inscripcion',label:'Mi inscripción',icon:'FileText'}]:[]),
 ...(session.user?.role!=='student'?[{to:'/informes',label:'Informes y estadísticas',icon:'ChartNoAxesCombined'}]:[]),
 {to:'/perfil',label:'Mi perfil',icon:'UserRound'}
])
const initials=computed(()=>session.user?.name?.split(' ').slice(0,2).map(x=>x[0]).join('')||'UL')
const role=computed(()=>({admin:'Administrador',teacher:'Docente',student:'Estudiante'}[session.user?.role]))
function toggleMenu(){if(smallScreen.value)mobile.value=!mobile.value;else collapsed.value=!collapsed.value}
function celebrate({firstAccess}){if(firstAccess)welcomeOpen.value=true}
async function leave(){welcomeOpen.value=false;if(leaving.value)return;leaving.value=true;exitError.value='';try{await logout();mobile.value=false;await router.replace('/login')}catch(e){exitError.value=e.message}finally{leaving.value=false}}
</script>

<template>
 <v-app>
  <router-view v-if="auth"/>
  <div v-else class="app-layout" :class="{'menu-collapsed':collapsed&&!smallScreen}">
   <aside class="sidebar" :class="{open:mobile}">
    <div class="sidebar-brand-row"><Brand light/></div>
    <div class="workspace-label">ESPACIO DE TRABAJO</div>
    <nav aria-label="Navegación principal"><router-link v-for="link in links" :key="link.to" :to="link.to" :title="collapsed?link.label:undefined" @click="mobile=false" :class="{active:route.path===link.to}"><Icon :name="link.icon"/><span class="nav-label">{{link.label}}</span><span v-if="route.path===link.to" class="nav-dot"></span></router-link></nav>
    <div class="sidebar-bottom"><div class="security-note"><span class="security-icon"><Icon name="ShieldCheck"/></span><strong>Tu información, segura</strong><p>{{session.user?.role==='admin'?'Acceso administrativo con contraseña personal.':'Acceso protegido con verificación en dos pasos.'}}</p><span class="secure-badge"><span></span> Protección activa</span></div><button class="logout" :disabled="leaving" title="Salir del sistema" @click="leave"><Icon name="LogOut"/><span class="nav-label">Cerrar sesión</span></button></div>
   </aside>
   <div v-if="mobile" class="sidebar-backdrop" @click="mobile=false"></div>
   <main class="main-shell">
    <header class="topbar"><div class="breadcrumb"><button class="shell-menu-toggle" aria-label="Expandir o contraer menú" :aria-expanded="smallScreen?mobile:!collapsed" @click="toggleMenu"><Icon name="Menu"/></button><img class="topbar-institution-logo" src="/logo-uls.png" alt="Universidad Luterana Salvadoreña" width="90" height="39"/><span>Portal académico</span><Icon name="ChevronRight" :size="14"/><strong>{{links.find(x=>x.to===route.path)?.label||'Resumen'}}</strong></div><div class="topbar-right"><span class="cycle-badge"><span></span> Ciclo II · 2026</span><div class="topbar-divider"></div><router-link to="/perfil" class="profile-topbar-link" aria-label="Abrir mi perfil"><span class="avatar">{{initials}}</span><span class="user-info"><strong>{{session.user?.name}}</strong><small>{{role}}</small></span></router-link><v-btn class="topbar-exit" variant="tonal" color="primary" :loading="leaving" @click="leave"><Icon name="LogOut" :size="17" class="mr-2"/>Salir del sistema</v-btn></div></header>
    <div class="page-content"><v-alert v-if="exitError" type="error" class="mb-4">{{exitError}}</v-alert><template v-if="requiredPassword"><div class="page-heading"><div><span class="eyebrow">BIENVENIDO A LA COMUNIDAD ULS</span><h1>Tu panel académico<span class="heading-dot">.</span></h1><p>Hola, {{session.user?.name}}. Completa tu primer acceso para continuar.</p></div></div><section class="welcome-banner"><div class="welcome-copy"><span class="banner-kicker">UNIVERSIDAD LUTERANA SALVADOREÑA</span><h2>Tu nueva etapa<br>comienza aquí.</h2><p>Estamos felices de acompañarte en este camino.</p></div></section></template><router-view v-else/></div>
    <footer class="app-footer"><span>© 2026 Universidad Luterana Salvadoreña</span><span>Consulta de Notas <span class="footer-dot">·</span> Portal académico</span></footer>
   </main>
   <PasswordDialog :model-value="requiredPassword" required @leave="leave" @completed="celebrate"/>
   <WelcomeCelebration v-model="welcomeOpen"/>
  </div>
 </v-app>
</template>
