import { expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import MoneyInput from '../components/ui/MoneyInput.vue'
import DomainField from '../components/DomainField.vue'
import { formatMoneyInput, parseMoneyInput } from '../utils/moneyInput'

it('formats API amounts with dots for thousands and a comma for the fraction', () => {
  expect(formatMoneyInput('1500000')).toBe('1.500.000')
  expect(formatMoneyInput('1500000.00')).toBe('1.500.000')
  expect(formatMoneyInput('1500000.50')).toBe('1.500.000,5')
  expect(formatMoneyInput('999.99')).toBe('999,99')
  expect(formatMoneyInput(1)).toBe('1')
  expect(formatMoneyInput('')).toBe('')
  expect(formatMoneyInput(null)).toBe('')
})

it('reads typed text back into the API form, ignoring grouping dots', () => {
  expect(parseMoneyInput('1500000')).toEqual({ value: '1500000', text: '1.500.000' })
  expect(parseMoneyInput('1.500.000')).toEqual({ value: '1500000', text: '1.500.000' })
  expect(parseMoneyInput('1.5000')).toEqual({ value: '15000', text: '15.000' })
  // Deleting the last digit of "1.250" leaves "1.25": still a whole number, not 1,25.
  expect(parseMoneyInput('1.25')).toEqual({ value: '125', text: '125' })
  expect(parseMoneyInput('1.500.000,')).toEqual({ value: '1500000', text: '1.500.000,' })
  expect(parseMoneyInput('1.500.000,509')).toEqual({ value: '1500000.50', text: '1.500.000,50' })
  // A pasted "500000.00" keeps its decimal point: 4+ digits before a dot is never grouping.
  expect(parseMoneyInput('500000.00')).toEqual({ value: '500000.00', text: '500.000,00' })
  expect(parseMoneyInput('12 000 đ')).toEqual({ value: '12000', text: '12.000' })
  expect(parseMoneyInput('0007')).toEqual({ value: '7', text: '7' })
  expect(parseMoneyInput('abc')).toEqual({ value: '', text: '' })
  expect(parseMoneyInput('')).toEqual({ value: '', text: '' })
})

it('MoneyInput shows grouped digits while typing and emits the plain amount', async () => {
  const wrapper = mount(MoneyInput, { props: { modelValue: '1250000.00' } })
  const input = wrapper.get('input')
  expect(input.element.value).toBe('1.250.000')
  expect(input.attributes('inputmode')).toBe('decimal')
  await input.setValue('12500000')
  expect(input.element.value).toBe('12.500.000')
  expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['12500000'])
  // A new value from outside (reset, quick-bid chip) is shown grouped.
  await wrapper.setProps({ modelValue: '260000.00' })
  await nextTick()
  expect(input.element.value).toBe('260.000')
  await wrapper.setProps({ modelValue: '' })
  expect(input.element.value).toBe('')
})

it('DomainField type="money" keeps the label, ids and errors and uses the money input', async () => {
  const wrapper = mount(DomainField, {
    props: {
      name: 'price',
      label: 'Giá bán (₫)',
      type: 'money',
      modelValue: '1',
      required: true,
      errors: ['Số tiền phải từ 1 ₫ trở lên.'],
    },
  })
  const input = wrapper.get('input#price')
  expect(input.attributes('type')).toBe('text')
  expect(input.attributes('aria-describedby')).toBe('price-errors')
  expect(input.element.value).toBe('1')
  await input.setValue('2.000.000')
  expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['2000000'])
  expect(wrapper.get('#price-errors').text()).toBe('Số tiền phải từ 1 ₫ trở lên.')
})
