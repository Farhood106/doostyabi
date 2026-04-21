<?php ob_start(); $csrf = app()->make(App\Security\Csrf::class); ?>
<h2><?= htmlspecialchars(t('onboarding.profile_title'), ENT_QUOTES, 'UTF-8') ?></h2>
<form method="post" action="/onboarding/profile">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8') ?>">
<div class="row">
<label><?= htmlspecialchars(t('onboarding.birth_year'), ENT_QUOTES, 'UTF-8') ?><input name="birth_year" value="<?= htmlspecialchars((string)old('birth_year'), ENT_QUOTES, 'UTF-8') ?>"><?php if($e=fieldError($errors ?? [],'birth_year')):?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif;?></label>
<label><?= htmlspecialchars(t('onboarding.age_min_pref'), ENT_QUOTES, 'UTF-8') ?><input name="age_min_pref" value="<?= htmlspecialchars((string)old('age_min_pref'), ENT_QUOTES, 'UTF-8') ?>"><?php if($e=fieldError($errors ?? [],'age_min_pref')):?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif;?></label>
<label><?= htmlspecialchars(t('onboarding.age_max_pref'), ENT_QUOTES, 'UTF-8') ?><input name="age_max_pref" value="<?= htmlspecialchars((string)old('age_max_pref'), ENT_QUOTES, 'UTF-8') ?>"><?php if($e=fieldError($errors ?? [],'age_max_pref')):?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif;?></label>
<label><?= htmlspecialchars(t('onboarding.gender_identity'), ENT_QUOTES, 'UTF-8') ?><input name="gender_identity" value="<?= htmlspecialchars((string)old('gender_identity'), ENT_QUOTES, 'UTF-8') ?>"></label>
<label><?= htmlspecialchars(t('onboarding.interested_in_gender'), ENT_QUOTES, 'UTF-8') ?><input name="interested_in_gender" value="<?= htmlspecialchars((string)old('interested_in_gender'), ENT_QUOTES, 'UTF-8') ?>"></label>
<label><?= htmlspecialchars(t('onboarding.country_code'), ENT_QUOTES, 'UTF-8') ?><input name="country_code" value="<?= htmlspecialchars((string)old('country_code'), ENT_QUOTES, 'UTF-8') ?>"><?php if($e=fieldError($errors ?? [],'country_code')):?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif;?></label>
<label><?= htmlspecialchars(t('onboarding.region_code'), ENT_QUOTES, 'UTF-8') ?><input name="region_code" value="<?= htmlspecialchars((string)old('region_code'), ENT_QUOTES, 'UTF-8') ?>"></label>
<label><?= htmlspecialchars(t('onboarding.location_cell_l5'), ENT_QUOTES, 'UTF-8') ?><input name="location_cell_l5" value="<?= htmlspecialchars((string)old('location_cell_l5'), ENT_QUOTES, 'UTF-8') ?>"></label>
<label><?= htmlspecialchars(t('onboarding.location_cell_l4'), ENT_QUOTES, 'UTF-8') ?><input name="location_cell_l4" value="<?= htmlspecialchars((string)old('location_cell_l4'), ENT_QUOTES, 'UTF-8') ?>"></label>
<label><?= htmlspecialchars(t('onboarding.distance_radius_km'), ENT_QUOTES, 'UTF-8') ?><input name="distance_radius_km" value="<?= htmlspecialchars((string)old('distance_radius_km', '30'), ENT_QUOTES, 'UTF-8') ?>"><?php if($e=fieldError($errors ?? [],'distance_radius_km')):?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endif;?></label>
</div>
<label><?= htmlspecialchars(t('onboarding.about_me'), ENT_QUOTES, 'UTF-8') ?><textarea name="about_me"><?= htmlspecialchars((string)old('about_me'), ENT_QUOTES, 'UTF-8') ?></textarea></label>
<label><?= htmlspecialchars(t('onboarding.looking_for'), ENT_QUOTES, 'UTF-8') ?><textarea name="looking_for"><?= htmlspecialchars((string)old('looking_for'), ENT_QUOTES, 'UTF-8') ?></textarea></label>
<button class="btn" type="submit"><?= htmlspecialchars(t('common.save_continue'), ENT_QUOTES, 'UTF-8') ?></button>
</form>
<?php $content = ob_get_clean(); require __DIR__ . '/../layouts/main.php'; ?>
