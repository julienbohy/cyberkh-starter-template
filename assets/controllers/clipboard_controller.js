import { Controller } from '@hotwired/stimulus';

/*
 * Copies the value of its "source" target (an input) to the clipboard.
 */
export default class extends Controller {
    static targets = ['source', 'done'];

    async copy() {
        await navigator.clipboard.writeText(this.sourceTarget.value);
        if (this.hasDoneTarget) {
            this.doneTarget.hidden = false;
            setTimeout(() => { this.doneTarget.hidden = true; }, 2000);
        }
    }
}
