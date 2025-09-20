# WP Self Assessment - Plugin de Autoevaluación con IA

Un plugin completo para WordPress que permite a los profesores configurar materias y a los estudiantes realizar autoevaluaciones interactivas utilizando la API de Google Gemini.

## 🚀 Características Principales

### Para Profesores (Back-office)
- **Configuración de API**: Integración con Google Gemini para generación de preguntas
- **Gestión de Materias**: Crear y administrar materias con grados y descripciones
- **Análisis de PDFs**: Carga de programas de curso en PDF para análisis automático con IA
- **Reportes Detallados**: Visualización de autoevaluaciones con filtros y estadísticas
- **Configuración de reCAPTCHA**: Protección contra bots y uso excesivo

### Para Estudiantes (Frontend)
- **Interfaz Intuitiva**: Sistema de pasos para configurar la autoevaluación
- **Niveles de Dificultad**:
  - **Inicial 🌱**: Preguntas extremadamente fáciles, casi obvias. Ideal para principiantes y considerando neurodivergencias
  - **Intermedio ⚡**: Preguntas que requieren comprensión sólida del tema
  - **Avanzado 🚀**: Preguntas técnicas complejas, ideal para pruebas técnicas de programación

- **Múltiples Modalidades**:
  - Preguntas Simples: Reflexión sobre conceptos clave
  - Ejercicios Prácticos: Problemas para resolver paso a paso
  - Análisis de Código: Revisión de fragmentos de código
- **Evaluación en Tiempo Real**: Respuestas evaluadas instantáneamente por IA
- **Recomendaciones Personalizadas**: Sugerencias basadas en el rendimiento
- **Protección reCAPTCHA**: Verificación de seguridad opcional

## 📋 Requisitos del Sistema

- WordPress 5.0 o superior
- PHP 7.4 o superior
- MySQL 5.6 o superior
- API Key de Google Gemini
- (Opcional) Claves de reCAPTCHA v3

## 🛠️ Instalación

1. **Subir el Plugin**:
   - Comprimir la carpeta `wp-self-assessment` en un archivo ZIP
   - Ir a WordPress Admin → Plugins → Añadir nuevo → Subir plugin
   - Seleccionar el archivo ZIP y activar

2. **Configurar la API**:
   - Ir a Autoevaluaciones → Configuración
   - Ingresar tu API Key de Gemini
   - (Opcional) Configurar reCAPTCHA

3. **Crear Materias**:
   - Ir a Autoevaluaciones → Materias
   - Hacer clic en "Agregar Nueva"
   - Completar la información de la materia

## 🔧 Configuración Inicial

### 1. Obtener API Key de Gemini

1. Visita [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Inicia sesión con tu cuenta de Google
3. Crea una nueva API Key
4. Copia la clave y pégala en la configuración del plugin

### 2. Configurar reCAPTCHA v3 (Opcional)

1. Visita [Google reCAPTCHA](https://www.google.com/recaptcha/admin)
2. Crea un nuevo sitio
3. Selecciona **reCAPTCHA v3** → **Invisible reCAPTCHA**
4. Agrega tu dominio
5. Copia las claves Site Key y Secret Key
6. Pégalas en la configuración del plugin

**Nota**: reCAPTCHA v3 es invisible y no requiere interacción del usuario.

### 3. Crear tu Primera Materia

1. Ve a **Autoevaluaciones → Materias**
2. Haz clic en **"Agregar Nueva"**
3. Completa los campos:
   - **Nombre**: Nombre de la materia
   - **Grado**: Nivel académico (ej: "1er Año", "Bachillerato")
   - **Descripción**: Objetivos y contenido de la materia
   - **Temario Manual**: Temas del curso (opcional)
   - **URL del PDF**: Programa de curso en PDF (opcional)

4. Si subiste un PDF, haz clic en **"Analizar PDF con IA"** para generar el temario automáticamente

## 📝 Uso del Plugin

### Para Profesores

#### Gestión de Materias
- **Ver Lista**: Visualiza todas las materias con filtros por grado
- **Editar**: Modifica información de materias existentes
- **Eliminar**: Borra materias (se eliminan también las autoevaluaciones asociadas)

#### Análisis de PDFs
1. Sube la URL de tu programa de curso en PDF
2. Haz clic en "Analizar PDF con IA"
3. Revisa el temario generado automáticamente
4. Acepta o edita según sea necesario

#### Reportes y Estadísticas
- **Dashboard**: Resumen general de autoevaluaciones
- **Filtros**: Por materia, fecha, estudiante
- **Exportación**: CSV y Excel (próximamente)
- **Detalles**: Información completa de cada autoevaluación

### Para Estudiantes

#### Usar el Shortcode
Incluye el shortcode en cualquier página o entrada:

```
[wpsa_autoevaluacion]
```

**Parámetros opcionales**:
```
[wpsa_autoevaluacion materia_id="1" tema="Álgebra" modalidad="ejercicios"]
```

#### Proceso de Autoevaluación

1. **Seleccionar Materia**: Elige de la lista de materias disponibles
2. **Configurar Evaluación**:
   - Ingresa tu nombre (opcional)
   - Especifica un tema (opcional)
   - **Selecciona nivel de dificultad** (Inicial/Intermedio/Avanzado)
   - Selecciona modalidad de evaluación
3. **Responder Preguntas**: 
   - Lee cada pregunta generada por IA
   - Las preguntas aumentan gradualmente en dificultad
   - Escribe tu respuesta
   - Recibe feedback inmediato
4. **Ver Resultados**: 
   - Puntuación final
   - Recomendaciones personalizadas
   - Opción de nueva evaluación

## 🎯 Niveles de Dificultad

### Inicial 🌱
- **Preguntas extremadamente fáciles**, casi obvias
- Ideal para principiantes y estudiantes con neurodivergencias
- Lenguaje simple y directo
- Respuestas evidentes para conocimiento básico
- Progresión gradual de dificultad

### Intermedio ⚡
- Preguntas que requieren comprensión sólida
- Nivel estándar de evaluación académica
- Demuestra aplicación de conceptos
- Progresión moderada de dificultad

### Avanzado 🚀
- Preguntas técnicas complejas
- Ideal para pruebas técnicas de programación
- Requiere dominio experto del tema
- Progresión rápida de dificultad

## 🎯 Modalidades de Evaluación

### Preguntas Simples
- Preguntas directas sobre conceptos
- Fomenta la reflexión crítica
- Ideal para repaso de teoría

### Ejercicios Prácticos
- Problemas matemáticos o técnicos
- Requiere resolución paso a paso
- Perfecto para materias cuantitativas

### Análisis de Código
- Revisión de fragmentos de código
- Identificación de errores
- Propuesta de mejoras
- Ideal para programación

## ⚙️ Configuraciones Avanzadas

### Límites de Preguntas
- Configura el máximo de preguntas por sesión
- Valor por defecto: 10 preguntas
- Rango recomendado: 5-20 preguntas

### Personalización de reCAPTCHA
- Protección contra bots
- Configuración opcional
- Recomendado para sitios públicos

### Shortcode Personalizado
```php
// Ejemplo de uso en template
echo do_shortcode('[wpsa_autoevaluacion materia_id="2" modalidad="codigo"]');
```

## 🔒 Seguridad y Privacidad

- **Datos Encriptados**: Toda la información se almacena de forma segura
- **reCAPTCHA**: Protección contra uso malicioso
- **Límites de Uso**: Control de preguntas por sesión
- **API Segura**: Comunicación encriptada con Gemini

## 📊 Base de Datos

El plugin crea las siguientes tablas:

- `wp_wpsa_materias`: Información de materias
- `wp_wpsa_autoevaluaciones`: Datos de evaluaciones
- `wp_wpsa_preguntas`: Preguntas individuales
- `wp_wpsa_configuraciones`: Configuraciones del sistema

## 🐛 Solución de Problemas

### Error: "API Key no configurada"
- Verifica que hayas ingresado correctamente la API Key de Gemini
- Asegúrate de que la clave sea válida y activa

### Error: "No hay materias disponibles"
- Crea al menos una materia en el panel de administración
- Verifica que la materia esté publicada

### Error: "reCAPTCHA fallido"
- Verifica las claves de reCAPTCHA v3
- Asegúrate de que el dominio esté registrado en Google reCAPTCHA
- Asegúrate de que el dominio esté registrado correctamente

### Preguntas no se generan
- Verifica la conexión a internet
- Revisa los logs de error de WordPress
- Confirma que la API Key tenga permisos suficientes

## 🔄 Actualizaciones

### Versión 1.0.0
- Lanzamiento inicial
- Integración con Gemini API
- Sistema completo de autoevaluación
- Panel de administración completo
- Soporte para reCAPTCHA

## 📞 Soporte

Para soporte técnico o reportar problemas:

1. Revisa la documentación completa
2. Verifica los logs de error de WordPress
3. Contacta al desarrollador del plugin

## 📄 Licencia

Este plugin está licenciado bajo GPL v2 o posterior.

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature
3. Commit tus cambios
4. Push a la rama
5. Abre un Pull Request

## 📈 Roadmap

### Próximas Versiones
- [ ] Exportación a PDF de resultados
- [ ] Integración con más APIs de IA
- [ ] Sistema de badges y logros
- [ ] Análisis avanzado de rendimiento
- [ ] Integración con LMS populares
- [ ] API REST para desarrolladores

---

**Desarrollado por Guía en tu Viaje**  
*Transformando la educación con tecnología*
