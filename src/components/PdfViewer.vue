<script setup>
import {ref,onMounted,watch,onBeforeUnmount,nextTick} from 'vue'
import {getDocument,GlobalWorkerOptions} from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import Icon from './Icon.vue'
GlobalWorkerOptions.workerSrc=workerUrl
const props=defineProps({url:String,label:{type:String,default:'Contenido del documento PDF'}}),canvas=ref(null),page=ref(1),pages=ref(0),error=ref(''),loading=ref(false)
let document=null,renderTask=null,loadTask=null
async function render(){if(!document||!canvas.value)return;loading.value=true;try{if(renderTask){renderTask.cancel();await renderTask.promise.catch(()=>{});}const p=await document.getPage(page.value);const viewport=p.getViewport({scale:1.35});canvas.value.width=viewport.width;canvas.value.height=viewport.height;renderTask=p.render({canvasContext:canvas.value.getContext('2d'),viewport});await renderTask.promise}catch(e){if(e.name!=='RenderingCancelledException')error.value='No se pudo visualizar el PDF. Puedes descargarlo con el botón superior.'}finally{loading.value=false}}
async function load(){try{loadTask=getDocument(props.url);document=await loadTask.promise;pages.value=document.numPages;page.value=1;await nextTick();await render()}catch(e){error.value='No se pudo cargar el PDF. Puedes descargarlo con el botón superior.'}}
watch(page,render);onMounted(load);onBeforeUnmount(()=>{renderTask?.cancel();loadTask?.destroy()})
</script>
<template><div class="pdf-viewer"><div class="pdf-controls"><button :disabled="page<=1" @click="page--" aria-label="Página anterior"><Icon name="ChevronLeft"/></button><span>Página {{page}} de {{pages||'…'}}</span><button :disabled="page>=pages" @click="page++" aria-label="Página siguiente"><Icon name="ChevronRight"/></button></div><v-progress-linear v-if="loading" indeterminate color="primary"/><v-alert v-if="error" type="error">{{error}}</v-alert><canvas ref="canvas" :aria-label="label"></canvas></div></template>
