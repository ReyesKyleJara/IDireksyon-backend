                    <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm font-medium">Quantity (optional)
                                <input type="number" min="1" max="65535" step="1" x-model="item.quantity" class="admin-input mt-1">
                            </label>
                        <label class="block text-sm font-medium">Submission format
                            <select x-model="item.submission_format" class="admin-input mt-1">
                                @foreach(\App\Models\GovernmentIdRequirementItem::FORMATS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label x-show="item.submission_format === 'custom'" class="block text-sm font-medium">Describe the format
                            <input x-model="item.submission_format_custom" maxlength="255" class="admin-input mt-1">
                        </label>
                        <label x-show="['original', 'photocopy', 'original_photocopy', 'certified_true_copy', 'custom'].includes(item.submission_format)" class="block text-sm font-medium">
                            <span x-text="item.submission_format === 'original_photocopy' ? 'Number of photocopies (optional)' : 'Number of copies (optional)'"></span>
                            <input type="number" min="1" max="65535" step="1" x-model="item.copies" class="admin-input mt-1">
                        </label>
                        <label class="block text-sm font-medium sm:col-span-2">Instructions
                            <textarea x-model="item.instructions" rows="2" maxlength="10000" class="admin-input mt-1"></textarea>
                        </label>
                    </div>
