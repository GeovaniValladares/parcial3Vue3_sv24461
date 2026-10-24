<script setup>
import { computed } from 'vue'
import { session } from '../api'
import Icon from './Icon.vue'
defineProps({modelValue:Boolean})
const emit=defineEmits(['update:modelValue'])
const name=computed(()=>session.user?.name?.split(' ')[0]||'')
const description=computed(()=>({student:'Tu aprendizaje tiene un nuevo espacio. Consulta tus notas, conoce tus grupos y encuentra tu boleta de inscripción.',teacher:'Gracias por acompañar el aprendizaje de nuestra comunidad. Tu espacio para gestionar grupos y evaluaciones ya está listo.',admin:'Tu espacio para acompañar y organizar nuestra comunidad académica ya está listo.'}[session.user?.role]))
const colors=['#2D52A8','#57CC99','#FFCB45','#A58BD5','#F5A364']
const confetti=Array.from({length:46},(_,i)=>({left:((i*47)%100)+'%',background:colors[i%colors.length],animationDelay:(i%9)*.11+'s',animationDuration:(2.3+(i%5)*.22)+'s','--confetti-turn':(i%2?480:-420)+'deg',borderRadius:i%3?'2px':'50%'}))
</script>
<template><v-dialog :model-value="modelValue" max-width="520" @update:model-value="emit('update:modelValue',$event)"><v-card class="welcome-celebration"><div class="welcome-confetti" aria-hidden="true"><i v-for="(style,i) in confetti" :key="i" :style="style"></i></div><button class="welcome-close" aria-label="Cerrar bienvenida" @click="emit('update:modelValue',false)"><Icon name="X"/></button><div class="welcome-success-symbol"><Icon name="GraduationCap" :size="47"/></div><span class="eyebrow">YA ERES PARTE DE LA COMUNIDAD ULS</span><h2>¡Bienvenido{{name?', '+name:''}}!</h2><p class="welcome-success-line" role="status"><Icon name="CircleCheck" :size="17"/> Tu contraseña se actualizó correctamente.</p><p class="welcome-description">{{description}}</p><div class="welcome-motto">Tu esfuerzo. Tu aprendizaje. Tu futuro.</div><v-btn color="primary" size="large" block @click="emit('update:modelValue',false)">Comenzar mi experiencia <Icon name="ArrowRight" :size="18" class="ml-3"/></v-btn></v-card></v-dialog></template>
