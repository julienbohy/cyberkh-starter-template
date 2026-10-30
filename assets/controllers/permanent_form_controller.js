import { Controller } from '@hotwired/stimulus';

/*
 * Keeps a `data-turbo-permanent` form untouched by stream-triggered refreshes (what is being
 * typed survives), but lets the page rendered after its own submit show the server form: the
 * reset fields after a success, the errors after a 422.
 *
 * On submit the element stops being permanent. A morph would then patch it in place, which
 * strips the widgets that live outside the server markup (Tom Select's wrapper) while their
 * Stimulus controllers stay connected, so the form is swapped whole once the morph is done:
 * controllers inside it disconnect and reconnect on the fresh markup. The server markup
 * carries the attribute again.
 */
export default class extends Controller {
    connect() {
        this._replacement = null;
        this._onSubmitStart = (event) => this._release(event);
        this._onSubmitEnd = (event) => this._restoreIfNothingRenders(event);
        this._onBeforeMorphElement = (event) => this._deferMorph(event);
        this._onMorph = () => this._swap();

        this.element.addEventListener('turbo:submit-start', this._onSubmitStart);
        this.element.addEventListener('turbo:submit-end', this._onSubmitEnd);
        this.element.addEventListener('turbo:before-morph-element', this._onBeforeMorphElement);
        document.addEventListener('turbo:morph', this._onMorph);
    }

    disconnect() {
        this.element.removeEventListener('turbo:submit-start', this._onSubmitStart);
        this.element.removeEventListener('turbo:submit-end', this._onSubmitEnd);
        this.element.removeEventListener('turbo:before-morph-element', this._onBeforeMorphElement);
        document.removeEventListener('turbo:morph', this._onMorph);
    }

    _release(event) {
        if (event.target === this.element) {
            this.element.removeAttribute('data-turbo-permanent');
        }
    }

    _restoreIfNothingRenders(event) {
        // A network error renders no page: stay permanent for the next refresh.
        if (event.target === this.element && !event.detail?.fetchResponse) {
            this.element.setAttribute('data-turbo-permanent', '');
        }
    }

    _deferMorph(event) {
        // No newElement: the form is being removed, let it go.
        if (event.target !== this.element || !event.detail?.newElement) {
            return;
        }
        event.preventDefault();
        this._replacement = event.detail.newElement;
    }

    _swap() {
        if (!this._replacement) {
            return;
        }
        const replacement = this._replacement;
        this._replacement = null;
        this.element.replaceWith(replacement);
    }
}
