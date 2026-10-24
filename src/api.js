import { reactive } from 'vue'
export const session=reactive({user:null,ready:false})
let csrf=''
export async function api(path, options={}) {
  const headers={Accept:'application/json',...options.headers}
  if(options.method && options.method!=='GET') {csrf=(await (await fetch('/api/auth/csrf',{credentials:'same-origin',headers:{Accept:'application/json'}})).json()).token;headers['X-CSRF-TOKEN']=csrf}
  if(options.body && !(options.body instanceof FormData)){headers['Content-Type']='application/json';options.body=JSON.stringify(options.body)}
  const response=await fetch('/api'+path,{...options,headers,credentials:'same-origin'})
  if(options.blob && response.ok)return response.blob()
  const data=await response.json().catch(()=>({message:'No se pudo conectar con el servidor.'}))
  if(!response.ok){if(response.status===419)csrf='';const error=new Error(response.status===419?'Tu sesión venció. Vuelve a intentarlo.':data.message||'Ocurrió un error.');error.errors=data.errors;error.status=response.status;throw error}
  return data
}
export async function loadSession(){try{session.user=(await api('/auth/me')).user}catch{session.user=null}finally{session.ready=true}}
export async function logout(){await api('/auth/logout',{method:'POST'});session.user=null}
