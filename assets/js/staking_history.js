// Simple client-side data and rendering for staking history and active stakes
(function(){
    const plans = window.__PLANS || {};

    // sample data (in a real app this would come from backend)
    let active = [
        {id: 's1', plan: 'starter', amount: 150, start: Date.now() - 5*24*3600*1000, end: Date.now() + 25*24*3600*1000, status: 'active'},
        {id: 's2', plan: 'growth', amount: 600, start: Date.now() - 10*24*3600*1000, end: Date.now() + 80*24*3600*1000, status: 'active'}
    ];

    let history = [
        {id:'h1', plan:'starter', amount:50, start: Date.now() - 60*24*3600*1000, end: Date.now() - 30*24*3600*1000, status:'completed'}
    ];

    function fmt(ts){
        const d = new Date(ts);
        return d.toLocaleDateString();
    }

    function renderActive(){
        const cont = document.getElementById('active-stakes');
        cont.innerHTML = '';
        if(active.length === 0){
            cont.innerHTML = '<div class="bg-darker rounded-3 p-3 empty-state">No active stakes.</div>';
            return;
        }
        active.forEach(s => {
            const p = plans[s.plan] || {title: s.plan};
            const card = document.createElement('div');
            card.className = 'p-3 bg-darker rounded-3 d-flex justify-content-between align-items-center card-accent';
            card.innerHTML = `
                <div>
                    <div class="fw-semibold">${p.title}</div>
                    <div class="small-muted small">${p.subtitle || ''} · Ends ${fmt(s.end)}</div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">${s.amount} USDT</div>
                    <div class="d-flex gap-2 mt-2 justify-content-end">
                        <button class="btn btn-sm btn-outline-light" data-action="view" data-id="${s.id}" aria-label="View stake ${p.title}">View</button>
                        <button class="btn btn-sm btn-danger" data-action="withdraw" data-id="${s.id}" aria-label="Withdraw stake ${p.title}">Withdraw</button>
                    </div>
                </div>`;
            cont.appendChild(card);
        });
    }

    function renderHistory(){
        const table = document.getElementById('history-table');
        const body = document.getElementById('history-body');
        const empty = document.getElementById('history-empty');
        if(history.length === 0){
            table.style.display = 'none'; empty.style.display = 'block'; return;
        }
        empty.style.display = 'none'; table.style.display = '';
        body.innerHTML = '';
        history.forEach(h => {
            const p = plans[h.plan] || {title: h.plan};
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${p.title}</td>
                <td>${h.amount} USDT</td>
                <td>${fmt(h.start)}</td>
                <td>${fmt(h.end)}</td>
                <td>${h.status}</td>`;
            body.appendChild(tr);
        });
    }

    function withdraw(id){
        const idx = active.findIndex(s=>s.id===id);
        if(idx === -1) return;
        const stake = active.splice(idx,1)[0];
        stake.status = 'withdrawn';
        history.unshift({ id: 'h'+Date.now(), plan: stake.plan, amount: stake.amount, start: stake.start, end: Date.now(), status: 'withdrawn' });
        renderActive(); renderHistory();
    }

    document.addEventListener('click', function(e){
        const btn = e.target.closest('button');
        if(!btn) return;
        const action = btn.getAttribute('data-action');
        const id = btn.getAttribute('data-id');
        if(action === 'withdraw'){
            if(!confirm('Withdraw this stake? This will move it to history.')) return;
            withdraw(id);
        } else if(action === 'view'){
            const stake = active.find(s=>s.id===id) || {};
            window.location.href = 'plan.php?plan=' + (stake.plan || 'starter');
        }
    });

    // initial render
    document.addEventListener('DOMContentLoaded', ()=>{
        renderActive(); renderHistory();
    });

})();
