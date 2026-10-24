<script setup>
import {computed} from 'vue'
import {Doughnut} from 'vue-chartjs'
import {Chart as ChartJS,ArcElement,Tooltip,Legend} from 'chart.js'
ChartJS.register(ArcElement,Tooltip,Legend)
const props=defineProps({passed:{default:0},failed:{default:0}})
const data=computed(()=>({labels:props.passed+props.failed?['Aprobados','Reprobados']:['Sin registros'],datasets:[{data:props.passed+props.failed?[props.passed,props.failed]:[1],backgroundColor:props.passed+props.failed?['#2D52A8','#B8C8ED']:['#E9EDF5'],borderWidth:0,spacing:5,borderRadius:6,hoverOffset:4}]}))
const percent=computed(()=>props.passed+props.failed?Math.round(props.passed/(props.passed+props.failed)*100):0)
const options={responsive:true,maintainAspectRatio:false,cutout:'80%',plugins:{legend:{display:false},tooltip:{enabled:true}},layout:{padding:5}}
</script>
<template><div class="chart-ring"><Doughnut :data="data" :options="options"/><div class="ring-center"><strong>{{percent}}<span>%</span></strong><small>de aprobación</small></div></div><div class="chart-legend"><div><span class="legend-dot"></span><span>Aprobados</span><strong>{{passed}}</strong></div><div><span class="legend-dot pale"></span><span>Reprobados</span><strong>{{failed}}</strong></div></div></template>
