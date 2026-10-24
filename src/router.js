import { createRouter, createWebHistory } from 'vue-router'
import {session,loadSession} from './api'
import AuthPage from './views/AuthPage.vue'
const Dashboard=()=>import('./views/Dashboard.vue')
const Records=()=>import('./views/Records.vue')
const Reports=()=>import('./views/Reports.vue')
const router=createRouter({history:createWebHistory(),routes:[
 {path:'/login',component:AuthPage,meta:{public:true}},
 {path:'/activar/:token',component:AuthPage,meta:{public:true}},
 {path:'/recuperar',component:AuthPage,meta:{public:true}},
 {path:'/restablecer/:token',component:AuthPage,meta:{public:true}},
 {path:'/cambiar-clave',redirect:'/'},
 {path:'/',component:Dashboard},
 {path:'/perfil',component:()=>import('./views/Profile.vue')},
 {path:'/inscripcion',component:()=>import('./views/Enrollment.vue'),meta:{student:true}},
 {path:'/notas',component:Records,props:{kind:'grades'}},
 {path:'/docentes',component:Records,props:{kind:'teachers'},meta:{admin:true}},
 {path:'/estudiantes',component:Records,props:{kind:'students'},meta:{admin:true}},
 {path:'/grupos',component:Records,props:{kind:'groups'}},
 {path:'/informes',component:Reports,meta:{staff:true}},
 {path:'/:pathMatch(.*)*',redirect:'/'}
]})
router.beforeEach(async to=>{if(!session.ready)await loadSession();if(!to.meta.public&&!session.user)return '/login';if(session.user?.must_change_password&&to.path!=='/'&&!to.meta.public)return '/';if(to.meta.admin&&session.user?.role!=='admin')return '/';if(to.meta.student&&session.user?.role!=='student')return '/';if(to.meta.staff&&session.user?.role==='student')return '/';if(to.path==='/login'&&session.user)return '/'})
export default router
