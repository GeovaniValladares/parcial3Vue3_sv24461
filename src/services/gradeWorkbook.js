import ExcelJS from 'exceljs'

const PALETTE={label:'FFDCE6F1',header:'FFA9C8E8',summary:'FFD9EFD0',text:'FF313945',border:'FFD7DFEA',blue:'FF2D52A8'}

export function buildGradeWorkbook({metadata,columns,rows}) {
 const workbook=new ExcelJS.Workbook()
 workbook.creator=String(metadata.find(([label])=>label==='Usuario que genera')?.[1]||'Consulta de Notas ULS')
 workbook.created=new Date()
 workbook.subject='Cuadro de notas ULS'
 workbook.title='Universidad Luterana Salvadoreña · Cuadro de notas'
 const sheet=workbook.addWorksheet('Cuadro de notas',{properties:{defaultRowHeight:23},pageSetup:{paperSize:9,orientation:'landscape',fitToPage:true,fitToWidth:1,fitToHeight:0,horizontalCentered:true,margins:{left:.25,right:.25,top:.4,bottom:.4,header:.15,footer:.15}}})
 const widths=[25,14,28,30,13,13,14,10,14,27]
 columns.forEach((_,index)=>{sheet.getColumn(index+1).width=widths[index]||20})
 const fill=argb=>({type:'pattern',pattern:'solid',fgColor:{argb}})
 const border={top:{style:'thin',color:{argb:PALETTE.border}},left:{style:'thin',color:{argb:PALETTE.border}},bottom:{style:'thin',color:{argb:PALETTE.border}},right:{style:'thin',color:{argb:PALETTE.border}}}
 metadata.forEach(([label,value],index)=>{
  const row=index+1
  const key=sheet.getCell(row,1)
  key.value=String(label)
  key.font={name:'Calibri',size:10,bold:true,color:{argb:PALETTE.text}}
  key.fill=fill(PALETTE.label)
  key.alignment={vertical:'middle',wrapText:true}
  key.border=border
  sheet.mergeCells(row,2,row,columns.length)
  const cell=sheet.getCell(row,2)
  // Assign strings as text, never as formulas, even if a name starts with '='.
  cell.value=typeof value==='number'?value:String(value??'')
  cell.font={name:'Calibri',size:10,color:{argb:PALETTE.text},bold:typeof value==='number'}
  cell.alignment={vertical:'middle',horizontal:'left',wrapText:true}
  cell.border=border
  if(label==='Estudiantes reportados'||label==='Notas registradas'){
   cell.fill=fill(PALETTE.summary)
   cell.font={name:'Calibri',size:10,bold:true,color:{argb:'FF3C6537'}}
  }
  sheet.getRow(row).height=25
 })
 sheet.getRow(metadata.length+1).height=9
 const headerRow=metadata.length+2
 const header=sheet.getRow(headerRow)
 header.values=columns.map(String)
 header.height=28
 header.eachCell(cell=>{cell.fill=fill(PALETTE.header);cell.font={name:'Calibri',size:10,bold:true,color:{argb:PALETTE.text}};cell.alignment={vertical:'middle',horizontal:'left',wrapText:true};cell.border=border})
 rows.forEach((values,index)=>{
  const row=sheet.getRow(headerRow+index+1)
  values.forEach((value,column)=>{
   const cell=row.getCell(column+1)
   if(column===0&&/^\d{4}-\d{2}-\d{2}$/.test(String(value))){cell.value=new Date(value+'T00:00:00Z');cell.numFmt='dd/mm/yyyy'}
   else if(column===7){cell.value=Number(value);cell.numFmt='0.00'}
   else cell.value=String(value??'')
   cell.font={name:'Calibri',size:10,color:{argb:column===8?(Number(values[7])>=6?'FF327D58':'FFB34545'):PALETTE.text}}
   cell.alignment={vertical:'middle',horizontal:[0,4,5,6,7].includes(column)?'center':'left',wrapText:true}
   cell.border=border
  })
  // Long student, subject and teacher names wrap rather than overlap adjacent cells.
  const lines=Math.max(...values.map((value,column)=>Math.ceil(String(value??'').length/(widths[column]-3))))
  row.height=Math.max(25,lines*15+8)
 })
 const lastRow=headerRow+rows.length
 sheet.autoFilter={from:{row:headerRow,column:1},to:{row:lastRow,column:columns.length}}
 sheet.views=[{state:'frozen',ySplit:headerRow,topLeftCell:'A'+(headerRow+1),showGridLines:true}]
 sheet.pageSetup.printArea='A1:J'+lastRow
 sheet.pageSetup.printTitlesRow=headerRow+':'+headerRow
 sheet.headerFooter.oddFooter='&L&8Universidad Luterana Salvadoreña&C&8Cuadro de notas ULS&R&8Página &P de &N'
 return workbook
}

export async function createGradeWorkbookBuffer(data){
 return buildGradeWorkbook(data).xlsx.writeBuffer()
}
