import test from 'node:test'
import assert from 'node:assert/strict'
import ExcelJS from 'exceljs'
import {buildGradeWorkbook,createGradeWorkbookBuffer} from '../src/services/gradeWorkbook.js'

const payload=()=>({
 metadata:[['Institución','Universidad Luterana Salvadoreña'],['Reporte','Cuadro de notas ULS'],['Rango de fechas','01/10/2026 - 31/10/2026'],['Usuario que genera','=Nombre de prueba'],['Fecha y hora de generación','24/10/2026 10:00'],['Estudiantes reportados',1],['Notas registradas',2],['Grupo consultado','WEB-01'],['Evaluación','Todas las evaluaciones'],['Ciclos académicos','II-2026']],
 columns:['Fecha','Carné','Estudiante','Asignatura','Grupo','Ciclo','Evaluación','Nota','Estado','Docente'],
 rows:[['2026-10-15','00123','=HYPERLINK("https://example.invalid")','Programación, aplicaciones web','WEB-01','II-2026','Parcial 1',8.5,'Aprobado','Docente ULS'],['2026-10-20','00123','Estudiante de prueba','Programación, aplicaciones web','WEB-01','II-2026','Parcial 2',5.4,'Reprobado','Docente ULS']]
})

test('xlsx round trip preserves styles, text safety, dates and numeric scores',async()=>{
 const buffer=await createGradeWorkbookBuffer(payload())
 assert.equal(buffer.subarray(0,2).toString(),'PK')
 const reopened=new ExcelJS.Workbook()
 await reopened.xlsx.load(buffer)
 const sheet=reopened.getWorksheet('Cuadro de notas')
 assert.equal(sheet.getCell('A1').value,'Institución')
 assert.equal(sheet.getCell('B1').value,'Universidad Luterana Salvadoreña')
 assert.equal(sheet.getCell('J1').master.address,'B1')
 assert.equal(sheet.getCell('A1').fill.fgColor.argb,'FFDCE6F1')
 assert.equal(sheet.getCell('B6').value,1)
 assert.equal(sheet.getCell('B6').fill.fgColor.argb,'FFD9EFD0')
 assert.equal(sheet.getCell('A12').value,'Fecha')
 assert.equal(sheet.getCell('A12').fill.fgColor.argb,'FFA9C8E8')
 assert.equal(sheet.getCell('A12').font.bold,true)
 assert.equal(sheet.getCell('B4').type,ExcelJS.ValueType.String)
 assert.equal(sheet.getCell('B4').value,'=Nombre de prueba')
 assert.equal(sheet.getCell('C13').type,ExcelJS.ValueType.String)
 assert.equal(sheet.getCell('C13').formula,undefined)
 assert.equal(sheet.getCell('B13').value,'00123')
 assert.equal(sheet.getCell('A13').value.toISOString().slice(0,10),'2026-10-15')
 assert.equal(sheet.getCell('A13').numFmt,'dd/mm/yyyy')
 assert.equal(sheet.getCell('H13').value,8.5)
 assert.equal(sheet.getCell('H13').numFmt,'0.00')
 assert.equal(sheet.getCell('C13').alignment.wrapText,true)
 assert.equal(sheet.getCell('J14').border.bottom.style,'thin')
 assert.equal(sheet.getColumn('A').width,25)
})

test('print setup fits one page wide, repeats the table header and includes the whole report',async()=>{
 const data=payload()
 data.rows=Array.from({length:130},(_,i)=>[...data.rows[i%2]])
 const buffer=await createGradeWorkbookBuffer(data)
 const reopened=new ExcelJS.Workbook()
 await reopened.xlsx.load(buffer)
 const sheet=reopened.getWorksheet('Cuadro de notas')
 assert.equal(sheet.pageSetup.orientation,'landscape')
 assert.equal(sheet.pageSetup.paperSize,9)
 assert.equal(sheet.pageSetup.fitToWidth,1)
 assert.equal(sheet.pageSetup.fitToHeight,0)
 assert.equal(sheet.pageSetup.printTitlesRow,'12:12')
 assert.match(sheet.pageSetup.printArea,/J\$?142/)
 assert.equal(sheet.views[0].ySplit,12)
 assert.equal(sheet.getCell('J142').value,'Docente ULS')
})

test('empty queries retain metadata and table headers without invented records',()=>{
 const data=payload();data.rows=[];data.metadata[5][1]=0;data.metadata[6][1]=0
 const sheet=buildGradeWorkbook(data).getWorksheet('Cuadro de notas')
 assert.equal(sheet.getCell('B6').value,0)
 assert.equal(sheet.getCell('B7').value,0)
 assert.equal(sheet.rowCount,12)
 assert.equal(sheet.getCell('J12').value,'Docente')
})
