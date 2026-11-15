
{# CSRF rendering belongs to the application template, not to wilycode/form-csrf.
   Always render getToken(), never getData() (untrusted submitted input). #}
@component csrf($form, $fieldName = '_csrf')
    @set('csrf', $form->getField($fieldName))
    <input type="hidden" name="{{ $csrf->getHtmlName() }}" value="{{ $csrf->getToken() }}">
@endcomponent

@component error_notice($field, $messageKey, $resource = 'validation')
    @if($field->getErrors()->hasErrors())
        <div class="notice notice-error" role="alert">{{ $messageKey | trans($resource) }}</div>
    @endif
@endcomponent

@component csrf_error($form, $fieldName = '_csrf')
    @form.error_notice($form->getField($fieldName), 'csrf_token.invalid')
@endcomponent

@component error_summary($form)
    @set('invalidFields', array_filter($form->getFields(), static fn($field): bool => !$field->isTransparent() && $field->getErrors()->hasErrors()))
    @if($invalidFields !== [] || $form->getGeneralErrors() !== [])
        <div class="notice notice-error form-error-summary" role="alert" tabindex="-1" data-error-summary>
            <strong>{{ 'form_errors.title' | gtrans }}</strong>
            <p>{{ 'form_errors.description' | gtrans }}</p>
        </div>
    @endif
@endcomponent

@component error($field)
    @if($field->getErrors()->hasErrors())
        <div id="{{ $field->getHtmlId() }}-error" class="field-error" role="alert">
            @foreach($field->getErrors()->getErrors() as $error)
                <p>{{ $error | validation_error }}</p>
            @endforeach
        </div>
    @endif
@endcomponent

@component input($field, $type = 'text', $autocomplete = null, $maxLength = null, $min = null, $max = null, $step = null)
    <input id="{{ $field->getHtmlId() }}" name="{{ $field->getHtmlName() }}" type="{{ $type }}" value="{{ (is_string($field->getData()) || is_int($field->getData()) || is_float($field->getData())) ? $field->getData() : '' }}" @if($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif @if($maxLength !== null) maxlength="{{ $maxLength }}" @endif @if($min !== null) min="{{ $min }}" @endif @if($max !== null) max="{{ $max }}" @endif @if($step !== null) step="{{ $step }}" @endif @if($field->isRequired()) required @endif @if($field->getErrors()->hasErrors()) aria-invalid="true" aria-describedby="{{ $field->getHtmlId() }}-error" @endif>
@endcomponent

@component input_field($field, $labelKey, $type = 'text', $autocomplete = null, $maxLength = null, $min = null, $max = null, $step = null)
    <div class="field">
        <label for="{{ $field->getHtmlId() }}">{{ $labelKey | trans }}</label>
        @form.input($field, type: $type, autocomplete: $autocomplete, maxLength: $maxLength, min: $min, max: $max, step: $step)
        @form.error($field)
    </div>
@endcomponent

@component text_field($field, $labelKey, $autocomplete = null, $maxLength = null)
    @form.input_field($field, $labelKey, type: 'text', autocomplete: $autocomplete, maxLength: $maxLength)
@endcomponent

@component email_field($field, $labelKey, $autocomplete = null, $maxLength = null)
    @form.input_field($field, $labelKey, type: 'email', autocomplete: $autocomplete, maxLength: $maxLength)
@endcomponent

@component number_field($field, $labelKey, $min = null, $max = null, $step = null)
    @form.input_field($field, $labelKey, type: 'number', min: $min, max: $max, step: $step)
@endcomponent

@component tel_field($field, $labelKey, $autocomplete = null, $maxLength = null)
    @form.input_field($field, $labelKey, type: 'tel', autocomplete: $autocomplete, maxLength: $maxLength)
@endcomponent

@component url_field($field, $labelKey, $autocomplete = null, $maxLength = null)
    @form.input_field($field, $labelKey, type: 'url', autocomplete: $autocomplete, maxLength: $maxLength)
@endcomponent

@component submit_button($labelKey = 'submit', $variant = 'primary')
    <button class="button button-{{ $variant }}" type="submit" data-submit-label>{{ $labelKey | trans }}</button>
@endcomponent

@component textarea_field($field, $labelKey, $rows = 6, $maxLength = null)
    <div class="field">
        <label for="{{ $field->getHtmlId() }}">{{ $labelKey | trans }}</label>
        <textarea id="{{ $field->getHtmlId() }}" name="{{ $field->getHtmlName() }}" rows="{{ $rows }}" @if($maxLength !== null) maxlength="{{ $maxLength }}" @endif @if($field->isRequired()) required @endif @if($field->getErrors()->hasErrors()) aria-invalid="true" aria-describedby="{{ $field->getHtmlId() }}-error" @endif>{{ is_string($field->getData()) ? $field->getData() : '' }}</textarea>
        @form.error($field)
    </div>
@endcomponent
