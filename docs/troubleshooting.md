# Troubleshooting

### Why can't I assign an object to a job queue?

If you want to assign an object to the queue, please make sure it implements `JsonSerializable` interface. This is how CodeIgniter [Entities](https://codeigniter.com/user_guide/models/entities.html) are handled by default.

You may ask, why not just use `serialize` and `unserialize`? There are security reasons that keep us from doing so. These functions are not safe to use with user provided data.

### I get an error when trying to install via composer.

Please see these [instructions](installation.md/#a-composer-error-occurred).
