import { createApp, h } from 'vue'
import { createVuetify } from 'vuetify'
import { VApp,VBtn,VTextField,VSelect,VCard,VAlert,VProgressLinear,VOtpInput,VTextarea,VDialog,VCardText,VPagination } from 'vuetify/components'
import { Ripple } from 'vuetify/directives'
import { es } from 'vuetify/locale'
import { Check,ChevronDown,ChevronUp,ChevronLeft,ChevronRight,ChevronsLeft,ChevronsRight,X,Info,TriangleAlert,CircleAlert,CheckSquare,Square,MinusSquare,ArrowUp,ArrowDown,Dot,CheckCircle } from 'lucide-vue-next'
import 'vuetify/styles'
import App from './App.vue'
import router from './router'
import './style.css'
import './auth-responsive.css'
import './auth-reference.css'
import './sidebar-theme.css'
const iconMap={complete:Check,success:CheckCircle,info:Info,warning:TriangleAlert,error:CircleAlert,close:X,clear:X,prev:ChevronLeft,next:ChevronRight,first:ChevronsLeft,last:ChevronsRight,dropdown:ChevronDown,expand:ChevronDown,collapse:ChevronUp,checkboxOn:CheckSquare,checkboxOff:Square,checkboxIndeterminate:MinusSquare,sortAsc:ArrowUp,sortDesc:ArrowDown,delimiter:Dot}
const vuetify=createVuetify({components:{VApp,VBtn,VTextField,VSelect,VCard,VAlert,VProgressLinear,VOtpInput,VTextarea,VDialog,VCardText,VPagination},directives:{Ripple},locale:{locale:'es',messages:{es}},icons:{defaultSet:'lucide',aliases:Object.fromEntries(Object.keys(iconMap).map(k=>[k,k])),sets:{lucide:{component:props=>h(props.tag||'span',{class:'v-icon'},[h(iconMap[props.icon]||ChevronDown,{size:20,'stroke-width':1.8})])}}},theme:{defaultTheme:'uls',themes:{uls:{dark:false,colors:{primary:'#313945',secondary:'#2D52A8',background:'#F4F7FD',surface:'#FFFFFF',error:'#EF5350',warning:'#FFEAA7',success:'#57CC99'}}}},defaults:{VBtn:{rounded:'lg',elevation:0},VTextField:{variant:'outlined',density:'comfortable',color:'primary'},VSelect:{variant:'outlined',density:'comfortable',color:'primary'},VCard:{rounded:'xl',elevation:0}}})
createApp(App).use(router).use(vuetify).mount('#app')
