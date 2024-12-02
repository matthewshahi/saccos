<div class="col-md-6 form-group mb-3">
    <label for="shortcode">Shortcode</label>
    <input 
        type="text" 
        class="form-control form-control-rounded @error('shortcode') is-invalid @enderror" 
        id="shortcode" 
        name="shortcode" 
        value="{{ old('shortcode', $config->shortcode ?? '') }}" 
        placeholder="Enter shortcode" 
        required>
    @error('shortcode')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-md-6 form-group mb-3">
    <label for="api_type">API Type</label>
    <input 
        type="text" 
        class="form-control form-control-rounded @error('api_type') is-invalid @enderror" 
        id="api_type" 
        name="api_type" 
        value="{{ old('api_type', $config->api_type ?? 'mpesa_express') }}" 
        placeholder="Enter API type" 
        required>
    @error('api_type')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-md-6 form-group mb-3">
    <label for="confirmation_url">Confirmation URL</label>
    <input 
        type="url" 
        class="form-control form-control-rounded @error('confirmation_url') is-invalid @enderror" 
        id="confirmation_url" 
        name="confirmation_url" 
        value="{{ old('confirmation_url', $config->confirmation_url ?? '') }}" 
        placeholder="Enter confirmation URL" 
        required>
    @error('confirmation_url')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-md-6 form-group mb-3">
    <label for="validation_url">Validation URL</label>
    <input 
        type="url" 
        class="form-control form-control-rounded @error('validation_url') is-invalid @enderror" 
        id="validation_url" 
        name="validation_url" 
        value="{{ old('validation_url', $config->validation_url ?? '') }}" 
        placeholder="Enter validation URL" 
        required>
    @error('validation_url')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-md-6 form-group mb-3">
    <label for="consumer_key">Consumer Key</label>
    <input 
        type="text" 
        class="form-control form-control-rounded @error('consumer_key') is-invalid @enderror" 
        id="consumer_key" 
        name="consumer_key" 
        value="{{ old('consumer_key', $config->consumer_key ?? '') }}" 
        placeholder="Enter consumer key" 
        required>
    @error('consumer_key')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-md-6 form-group mb-3">
    <label for="consumer_secret">Consumer Secret</label>
    <input 
        type="text" 
        class="form-control form-control-rounded @error('consumer_secret') is-invalid @enderror" 
        id="consumer_secret" 
        name="consumer_secret" 
        value="{{ old('consumer_secret', $config->consumer_secret ?? '') }}" 
        placeholder="Enter consumer secret" 
        required>
    @error('consumer_secret')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="col-md-6 form-group mb-3">
    <label for="passkey">Passkey</label>
    <input 
        type="text" 
        class="form-control form-control-rounded @error('passkey') is-invalid @enderror" 
        id="passkey" 
        name="passkey" 
        value="{{ old('passkey', $config->passkey ?? '') }}" 
        placeholder="Enter passkey" 
        required>
    @error('passkey')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>