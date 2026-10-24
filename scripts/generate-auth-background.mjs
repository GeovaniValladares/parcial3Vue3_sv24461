import fs from 'node:fs'

// Local, scalable network illustration. No remote images or runtime animation.
const width=1600,height=1000,rows=9,columns=22
const points=[]
for(let row=0;row<rows;row++){
 const line=[]
 for(let column=0;column<columns;column++){
  const x=-80+column*85+Math.sin(row*2.3+column)*20
  const y=460+row*75+Math.sin(column*.42+row*.28)*95+Math.cos(column*.17)*row*4
  line.push({x:Math.round(x),y:Math.round(y)})
 }
 points.push(line)
}
const edges=[],nodes=[]
function edge(a,b,opacity){edges.push(`<path d="M${a.x} ${a.y}L${b.x} ${b.y}" stroke="#5bd7f5" stroke-opacity="${opacity}" stroke-width="${opacity>.3?1.3:.7}"/>`)}
for(let row=0;row<rows;row++)for(let column=0;column<columns;column++){
 const point=points[row][column]
 if(column<columns-1)edge(point,points[row][column+1],.17+(column%4)*.05)
 if(row<rows-1){edge(point,points[row+1][column],.12+(row%3)*.04);if(column<columns-1&&(row+column)%3!==0)edge(point,points[row+1][column+1],.1+(column%3)*.04)}
 const bright=(row*7+column*3)%11===0
 nodes.push(`<circle cx="${point.x}" cy="${point.y}" r="${bright?3.3:1.5}" fill="${bright?'#ddfbff':'#56bfdc'}" opacity="${bright?.95:.5}"${bright?' filter="url(#glow)"':''}/>`)
}
const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid slice"><defs><radialGradient id="left"><stop stop-color="#075878" stop-opacity=".8"/><stop offset="1" stop-color="#03121d" stop-opacity="0"/></radialGradient><radialGradient id="right"><stop stop-color="#008bb1" stop-opacity=".7"/><stop offset="1" stop-color="#03121d" stop-opacity="0"/></radialGradient><linearGradient id="fade" x2="0" y2="1"><stop stop-color="white" stop-opacity=".3"/><stop offset="1" stop-color="white"/></linearGradient><filter id="glow" x="-300%" y="-300%" width="700%" height="700%"><feGaussianBlur stdDeviation="4" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter><filter id="soft"><feGaussianBlur stdDeviation="18"/></filter></defs><rect width="1600" height="1000" fill="#020e19"/><ellipse cx="170" cy="180" rx="650" ry="430" fill="url(#left)"/><ellipse cx="1470" cy="430" rx="620" ry="500" fill="url(#right)"/><ellipse cx="750" cy="1080" rx="950" ry="320" fill="url(#left)"/><g opacity=".45" filter="url(#soft)"><path d="M1150 520L1630 150" stroke="#077b9e" stroke-width="26"/><circle cx="160" cy="260" r="18" fill="#1685a0"/><circle cx="1350" cy="130" r="15" fill="#0e637c"/></g><g>${edges.join('')}${nodes.join('')}</g><g opacity=".35"><circle cx="150" cy="150" r="3" fill="#9eecff" filter="url(#glow)"/><circle cx="1300" cy="280" r="3" fill="#b3f3ff" filter="url(#glow)"/><circle cx="1450" cy="650" r="5" fill="#bdedff" filter="url(#glow)"/></g></svg>`
fs.mkdirSync('public/images',{recursive:true})
fs.writeFileSync('public/images/auth-network.svg',svg)
