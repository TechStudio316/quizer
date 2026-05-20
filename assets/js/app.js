function startQuizTimer(seconds, targetId, formId) {
    const target = document.getElementById(targetId);
    const form = document.getElementById(formId);
    if (!target || !form) return;

    let remaining = Number(seconds);
    const tick = () => {
        const minutes = Math.floor(remaining / 60);
        const secs = remaining % 60;
        target.textContent = `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        if (remaining <= 0) {
            form.submit();
            return;
        }
        remaining -= 1;
        window.setTimeout(tick, 1000);
    };
    tick();
}

function refreshEvery(seconds) {
    window.setTimeout(() => window.location.reload(), seconds * 1000);
}

function enableAnswerAutosave(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    const base = document.body.dataset.baseUrl || '';
    const postAnswer = (payload) => {
        fetch(`${base}/save_answer.php`, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        }).catch(() => {});
    };

    form.querySelectorAll('input[type="radio"][data-question-id]').forEach((input) => {
        input.addEventListener('change', () => {
            const payload = new FormData();
            payload.append('question_id', input.dataset.questionId);
            payload.append('selected_option', input.value);
            postAnswer(payload);
        });
    });

    form.querySelectorAll('input[type="text"][data-question-id]').forEach((input) => {
        let timeoutId = null;
        input.addEventListener('input', () => {
            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(() => {
                const payload = new FormData();
                payload.append('question_id', input.dataset.questionId);
                payload.append('answer_text', input.value);
                postAnswer(payload);
            }, 350);
        });
    });
}
