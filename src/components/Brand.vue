<script setup>defineProps({light:Boolean})</script>
<template><div class="brand institutional-brand" :class="{light}"><img :src="light?'/logo-uls-white.png':'/logo-uls.png'" alt="ULS · Universidad Luterana Salvadoreña" :width="light?755:1840" :height="light?330:802"/><small>PORTAL ACADÉMICO · CONSULTA DE NOTAS</small></div></template>
