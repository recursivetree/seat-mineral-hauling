importScripts("/mineralhauling/js/solver.js")

onmessage = function(d){
    const results = solver.Solve(d.data);
    postMessage(results);
};