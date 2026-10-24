<script setup>
import { computed } from 'vue'
import { GraduationCap, LayoutDashboard, BookOpen, Users, UserRound, Layers, ChartNoAxesCombined, LogOut, ArrowUpRight, ArrowRight, Plus, Search, ChevronRight, ChevronLeft, Download, Mail, Pencil, Trash2, X, Check, ShieldCheck, LockKeyhole, Eye, EyeOff, Menu, CalendarDays, FileText, CircleHelp, KeyRound, RefreshCw, CircleCheck, TriangleAlert, QrCode } from 'lucide-vue-next'
const props=defineProps({name:String,size:{default:20}})
const icons={GraduationCap,LayoutDashboard,BookOpen,Users,UserRound,Layers,ChartNoAxesCombined,LogOut,ArrowUpRight,ArrowRight,Plus,Search,ChevronRight,ChevronLeft,Download,Mail,Pencil,Trash2,X,Check,ShieldCheck,LockKeyhole,Eye,EyeOff,Menu,CalendarDays,FileText,CircleHelp,KeyRound,RefreshCw,CircleCheck,TriangleAlert,QrCode}
const icon=computed(()=>icons[props.name]||BookOpen)
</script>
<template><component :is="icon" :size="size" :stroke-width="1.8" aria-hidden="true" /></template>
