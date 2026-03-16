// Shared plan data and simple navigation handlers for investments
window.__PLANS = {
    starter: {
        id: 'starter', title: 'Starter', subtitle: 'Ideal for new stakers', yield: 6, duration: 30, min: 50,
        features: ['Fixed 6% return over 30 days', 'Daily accrual', 'Withdrawable after term']
    },
    growth: {
        id: 'growth', title: 'Growth', subtitle: 'Balanced returns and duration', yield: 12, duration: 90, min: 500,
        features: ['Higher returns for longer term', 'Bi-weekly payouts', 'Priority support']
    },
    pro: {
        id: 'pro', title: 'Pro', subtitle: 'For experienced stakers', yield: 20, duration: 180, min: 5000,
        features: ['Highest yield plan', 'Monthly accrual', 'Dedicated account manager']
    },
    enterprise: {
        id: 'enterprise', title: 'Enterprise', subtitle: 'Custom plans for institutions', yield: 0, duration: 0, min: 50000,
        features: ['Custom duration and returns', 'Institutional-grade custody', 'White-glove onboarding']
    }
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-plan]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const plan = e.currentTarget.getAttribute('data-plan');
            if (plan === 'enterprise' && e.currentTarget.innerText.toLowerCase().includes('contact')) {
                // contact action
                alert('Please contact sales@finpay.com for enterprise/staking plans.');
                return;
            }
            // navigate to plan details (staking)
            window.location.href = 'plan.php?plan=' + plan;
        });
    });
});
