<div class="mb-3">
    <label for="shortcode">Shortcode</label>
    <input type="text" class="form-control" id="shortcode" name="shortcode" value="{{ old('shortcode', $config->shortcode ?? '') }}" required>
</div>
<div class="mb-3">
    <label for="api_type">API Type</label>
    <input type="text" class="form-control" id="api_type" name="api_type" value="{{ old('api_type', $config->api_type ?? '') }}" required>
</div>
<div class="mb-3">
    <label for="response_type">Response Type</label>
    <input type="text" class="form-control" id="response_type" name="response_type" value="{{ old('response_type', $config->response_type ?? 'Completed') }}" required>
</div>
<div class="mb-3">
    <label for="confirmation_url">Confirmation URL</label>
    <input type="url" class="form-control" id="confirmation_url" name="confirmation_url" value="{{ old('confirmation_url', $config->confirmation_url ?? '') }}" required>
</div>
<div class="mb-3">
    <label for="validation_url">Validation URL</label>
    <input type="url" class="form-control" id="validation_url" name="validation_url" value="{{ old('validation_url', $config->validation_url ?? '') }}" required>
</div>
<div class="mb-3">
    <label for="consumer_key">Consumer Key</label>
    <input type="text" class="form-control" id="consumer_key" name="consumer_key" value="{{ old('consumer_key', $config->consumer_key ?? '') }}" required>
</div>
<div class="mb-3">
    <label for="consumer_secret">Consumer Secret</label>
    <input type="text" class="form-control" id="consumer_secret" name="consumer_secret" value="{{ old('consumer_secret', $config->consumer_secret ?? '') }}" required>
</div>
<div class="mb-3">
    <label for="passkey">Passkey</label>
    <input type="text" class="form-control" id="passkey" name="passkey" value="{{ old('passkey', $config->passkey ?? '') }}" required>
</div>