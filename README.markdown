**Author:** Stephen Korecky <br>
**Website:** http://stephenkorecky.com <br>
**Plugin Website:** https://github.com/skorecky/Add-Clear <br>
**NPM jQuery Plugin:** https://www.npmjs.com/package/add-clear <br>
_jQuery Plugin website is outdated and read-only now. Please use NPM_<br>
**jQuery Plugin:** http://plugins.jquery.com/add-clear/

## About

Add Clear is a jQuery plugin that adds a input clearing button on any input you
apply it to. It clears the value, and returns focus to that field.

## How to use

- Load jQuery into your project
- Load Add Clear plugin into your project
- Setup which elements you would like to apply this plugin to.

### Usage
```javascript
$(function(){
  $("input").addClear();
});

// Example onClear option usage
$("input").addClear({
  onClear: function(){
    alert("call back!");
  }
});
```
### Available Options

<table>
  <tr>
    <th>Option</th>
    <th>Default</th>
    <th>Type</th>
  </tr>
  <tr>
    <td>closeSymbol</td>
    <td>&#10006;</td>
    <td>string</td>
  </tr>
  <tr>
    <td>top</td>
    <td>1</td>
    <td>number</td>
  </tr>
  <tr>
    <td>right</td>
    <td>4</td>
    <td>number</td>
  </tr>
  <tr>
    <td>returnFocus</td>
    <td>true</td>
    <td>boolean</td>
  </tr>
  <tr>
    <td>showOnLoad</td>
    <td>false</td>
    <td>boolean</td>
  </tr>
  <tr>
    <td>hideOnBlur</td>
    <td>false</td>
    <td>boolean</td>
  </tr>
  <tr>
    <td>tabbable</td>
    <td>true</td>
    <td>boolean</td>
  </tr>
  <tr>
    <td>onClear</td>
    <td>null</td>
    <td>function</td>
  </tr>
  <tr>
    <td>paddingRight</td>
    <td>20px</td>
    <td>string</td>
  </tr>
</table>

### Note about Microsoft Browsers

The more modern Microsoft browsers (IE10+ and Edge) have built-in clear buttons that appear
automatically on text inputs. To prevent those buttons from interfering with Add Clear, you must
use the `::-ms-clear` CSS pseudo-element in your styles, as described here:

https://developer.mozilla.org/en-US/docs/Web/CSS/::-ms-clear
